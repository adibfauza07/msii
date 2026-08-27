<?php
// PERBAIKAN: Ubah path menjadi mundur 2 tingkat (../../)
require_once __DIR__ . "/../../config/database_ordering.php";

header("Content-Type: application/json");

// Pastikan variabel $conn dikenali
if (!isset($conn) || $conn === false) {
    echo json_encode(array());
    exit();
}

$q = isset($_POST["q"]) ? trim($_POST["q"]) : "";

if ($q == "") {
    echo json_encode(array());
    exit();
}

$like = "%" . $q . "%";
$startLike = $q . "%";

// MENGGABUNGKAN 3 TABEL (CUST, VENDOR, SUPPLIER) SEKALIGUS MENGGUNAKAN UNION ALL
$sql = "
    SELECT TOP 20
        CODE AS CUST_CODE,
        COMP AS CUST_COMP
    FROM (
        -- 1. Pencarian dari Tabel Customer (CUST)
        SELECT 
            CUST_CODE AS CODE,
            CUST_COMP AS COMP
        FROM CUST
        WHERE CUST_CODE LIKE ? OR CUST_COMP LIKE ?
        
        UNION ALL
        
        -- 2. Pencarian dari Tabel Vendor (VENDOR)
        SELECT 
            VEND_CODE AS CODE,
            VEND_COMP AS COMP
        FROM VENDOR 
        WHERE VEND_CODE LIKE ? OR VEND_COMP LIKE ?

        UNION ALL
        
        -- 3. Pencarian dari Tabel Supplier (SUPPLIER)
        SELECT 
            SUP_CODE AS CODE,
            SUP_COMP AS COMP
        FROM SUPPLIER 
        WHERE SUP_CODE LIKE ? OR SUP_COMP LIKE ?
    ) AS CombinedData
    ORDER BY
        CASE WHEN CODE LIKE ? THEN 0 ELSE 1 END,
        CODE
";

// Parameter disesuaikan dengan jumlah tanda tanya (?) di query:
// 2 param CUST + 2 param VENDOR + 2 param SUPPLIER + 1 param ORDER BY = 7 parameter
$params = array(
    $like, $like,       // Untuk CUST
    $like, $like,       // Untuk VENDOR
    $like, $like,       // Untuk SUPPLIER
    $startLike          // Untuk ORDER BY
);

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array());
    exit();
}

$data = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = array(
        // CUST_ID dan CUST_ABBR dikosongkan karena form_2.php hanya butuh CODE dan COMP
        "CUST_ID"   => '', 
        "CUST_CODE" => isset($row["CUST_CODE"]) ? trim($row["CUST_CODE"]) : '',
        "CUST_COMP" => isset($row["CUST_COMP"]) ? trim($row["CUST_COMP"]) : '',
        "CUST_ABBR" => ''
    );
}

echo json_encode($data);
?>