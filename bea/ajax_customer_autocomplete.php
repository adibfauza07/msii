<?php
// ajax_customer_autocomplete.php
// Endpoint customer autocomplete, kompatibel PHP 5.4.
require_once __DIR__ . '/config/database.php';

// 1. Wajib tambahkan header JSON agar frontend bisa mem-parsing data
header('Content-Type: application/json; charset=utf-8');

if (empty($configLoaded) && empty($conn)) {
    echo json_encode(array());
    exit;
}

$term = '';
// 2. Perluas penangkapan parameter untuk mencegah gagal tangkap dari frontend
if (isset($_POST['q'])) {
    $term = trim($_POST['q']);
} elseif (isset($_GET['term'])) {
    $term = trim($_GET['term']);
} elseif (isset($_GET['q'])) { 
    $term = trim($_GET['q']);
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
    // TIPS DEBUGGING: 
    // Jika masih kosong/tidak muncul, hapus tanda // pada baris di bawah ini 
    // untuk melihat error dari database di tab 'Network' Inspect Element browser Anda:
    // die(print_r(sqlsrv_errors(), true));
    
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
        // Format baru standar UI Autocomplete
        'id'      => $id,
        'code'    => $code,
        'company' => $company,
        'abbr'    => $abbr,
        'label'   => $label,
        'value'   => $label, // 3. WAJIB ADA: jQuery UI butuh 'value' saat item dipilih

        // Format lama agar kompatibel dengan JavaScript sebelumnya.
        'CUST_ID'   => $id,
        'CUST_CODE' => $code,
        'CUST_COMP' => $company,
        'CUST_ABBR' => $abbr
    );
}

echo json_encode($data);
exit;