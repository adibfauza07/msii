<?php
// search_sup_multi.php
// Autocomplete SUPPLIER dari P1/P2.

ob_start();

if (session_id() === '') {
    session_start();
}

require_once __DIR__ . "/../config/global.php";

$dbName = 'msData';

$serversConfig = array(
    'p1' => array('ip' => '192.168.0.4', 'short' => 'P1'),
    'p2' => array('ip' => '192.168.0.9', 'short' => 'P2')
);

function outputJson($data)
{
    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    echo json_encode($data);
    exit;
}

$q = isset($_POST['q']) ? trim($_POST['q']) : '';
$plant = isset($_POST['plant']) ? strtolower(trim($_POST['plant'])) : 'all';

if (!in_array($plant, array('all', 'p1', 'p2'), true)) {
    $plant = 'all';
}

if (
    $q === '' ||
    !isset($_SESSION['db_user']) ||
    trim((string)$_SESSION['db_user']) === ''
) {
    outputJson(array());
}

$uid = $_SESSION['db_user'];
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : '';

$keys = $plant === 'all' ? array('p1', 'p2') : array($plant);
$like = '%' . $q . '%';
$startLike = $q . '%';
$records = array();

foreach ($keys as $serverKey) {
    $options = array(
        'Database' => $dbName,
        'Uid' => $uid,
        'PWD' => $pwd,
        'CharacterSet' => 'UTF-8',
        'LoginTimeout' => 5
    );

    $conn = @sqlsrv_connect($serversConfig[$serverKey]['ip'], $options);

    if ($conn === false) {
        continue;
    }

    $sql = "
        SELECT TOP 20
            SUP_CODE,
            SUP_COMP
        FROM dbo.SUPPLIER
        WHERE SUP_CODE LIKE ?
           OR SUP_COMP LIKE ?
        ORDER BY
            CASE WHEN SUP_CODE LIKE ? THEN 0 ELSE 1 END,
            SUP_CODE
    ";

    $stmt = @sqlsrv_query(
        $conn,
        $sql,
        array($like, $like, $startLike)
    );

    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $code = isset($row['SUP_CODE'])
                ? trim((string)$row['SUP_CODE'])
                : '';

            $name = isset($row['SUP_COMP'])
                ? trim((string)$row['SUP_COMP'])
                : '';

            if ($code === '') {
                continue;
            }

            $key = $code . '|' . $name;

            if (!isset($records[$key])) {
                $records[$key] = array(
                    'SUP_CODE' => $code,
                    'SUP_COMP' => $name,
                    'plants' => array()
                );
            }

            $records[$key]['plants'][$serversConfig[$serverKey]['short']] = true;
        }

        sqlsrv_free_stmt($stmt);
    }

    sqlsrv_close($conn);
}

$result = array();

foreach ($records as $record) {
    $plants = array_keys($record['plants']);
    sort($plants);

    $result[] = array(
        'SUP_CODE' => $record['SUP_CODE'],
        'SUP_COMP' => $record['SUP_COMP'],
        'PLANT_SHORT' => implode('/', $plants)
    );
}

usort($result, function ($a, $b) {
    return strcmp($a['SUP_CODE'], $b['SUP_CODE']);
});

outputJson(array_slice($result, 0, 30));
