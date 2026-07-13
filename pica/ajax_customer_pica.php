<?php
// =====================================================
// ajax_customer_pica.php
// Autocomplete Customer untuk Form PICA
// PHP 5.4 compatible
// =====================================================

require_once __DIR__ . "/../config/database_p1.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// Helper untuk error messages
function get_sqlsrv_errors() {
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    $messages = array();
    if (is_array($errors)) {
        foreach ($errors as $error) {
            $messages[] = isset($error['message']) 
                ? (string)$error['message'] 
                : 'Kesalahan SQL Server tidak diketahui.';
        }
    }
    return $messages;
}

// Cek koneksi
if (!isset($conn) || $conn === false) {
    echo json_encode(array(
        "success" => false,
        "rows" => array(),
        "message" => "Koneksi database gagal."
    ));
    exit();
}

// ⚠️ PERBAIKAN 1: Gunakan GET, bukan POST
 $q = isset($_GET["q"]) ? trim((string)$_GET["q"]) : "";

if ($q === "") {
    echo json_encode(array(
        "success" => true,
        "rows" => array()
    ));
    exit();
}

 $like = "%" . $q . "%";

 $sql = "
    SELECT TOP 20
        CUST_CODE,
        CUST_COMP
    FROM CUST
    WHERE CUST_CODE LIKE ?
       OR CUST_COMP LIKE ?
    ORDER BY CUST_CODE ASC
";

 $params = array($like, $like);

 $stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    // ⚠️ PERBAIKAN 2: Return format yang konsisten
    echo json_encode(array(
        "success" => false,
        "rows" => array(),
        "message" => "Query gagal: " . implode(" | ", get_sqlsrv_errors())
    ));
    exit();
}

 $rows = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "CUST_CODE" => isset($row["CUST_CODE"]) ? trim((string)$row["CUST_CODE"]) : "",
        "CUST_COMP" => isset($row["CUST_COMP"]) ? trim((string)$row["CUST_COMP"]) : ""
    );
}

sqlsrv_free_stmt($stmt);

// ⚠️ PERBAIKAN 3: Return format sesuai yang diharapkan JavaScript
echo json_encode(array(
    "success" => true,
    "rows" => $rows
));
exit();
?>