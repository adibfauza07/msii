<?php

//ajax_customer_autocomplete.php
// Endpoint customer autocomplete, kompatibel PHP 5.4.
// Bisa dipakai oleh frontend lama (POST q) maupun frontend baru (GET term).

$configCandidates = array(
    __DIR__ . '/config/database_aging.php',
    __DIR__ . '/../config/database_aging.php',
    __DIR__ . '/config/database_ordering.php',
    __DIR__ . '/../config/database_ordering.php'
);

$configLoaded = false;
foreach ($configCandidates as $configFile) {
    if (file_exists($configFile)) {
        require_once $configFile;
        $configLoaded = true;
        break;
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!$configLoaded) {
    echo json_encode(array());
    exit;
}

$term = '';
if (isset($_POST['q'])) {
    $term = trim($_POST['q']);
} elseif (isset($_GET['term'])) {
    $term = trim($_GET['term']);
}

if ($term === '') {
    echo json_encode(array());
    exit;
}

$like = '%' . $term . '%';
$startLike = $term . '%';

$sql = "
    SELECT TOP 30
        CUST_ID,
        CUST_CODE,
        CUST_COMP,
        CUST_ABBR
    FROM CUST
    WHERE ISNULL(CUST_INACTIVE, 0) = 0
      AND (
            CUST_CODE LIKE ?
         OR CUST_COMP LIKE ?
         OR CUST_ABBR LIKE ?
      )
    ORDER BY
        CASE WHEN CUST_CODE LIKE ? THEN 0 ELSE 1 END,
        CUST_CODE
";

$params = array($like, $like, $like, $startLike);
$stmt = false;

if (function_exists('q')) {
    $stmt = q($sql, $params);
} elseif (isset($conn) && $conn !== false) {
    $stmt = sqlsrv_query($conn, $sql, $params);
}

if ($stmt === false) {
    echo json_encode(array());
    exit;
}

$data = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $id = isset($row['CUST_ID']) ? (string)$row['CUST_ID'] : '';
    $code = isset($row['CUST_CODE']) ? trim((string)$row['CUST_CODE']) : '';
    $company = isset($row['CUST_COMP']) ? trim((string)$row['CUST_COMP']) : '';
    $abbr = isset($row['CUST_ABBR']) ? trim((string)$row['CUST_ABBR']) : '';
    $label = $code . ($company !== '' ? ' - ' . $company : '');

    $data[] = array(
        // Format baru.
        'id' => $id,
        'code' => $code,
        'company' => $company,
        'abbr' => $abbr,
        'label' => $label,

        // Format lama agar kompatibel dengan JavaScript sebelumnya.
        'CUST_ID' => $id,
        'CUST_CODE' => $code,
        'CUST_COMP' => $company,
        'CUST_ABBR' => $abbr
    );
}

echo json_encode($data);
exit;
