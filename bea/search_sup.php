<?php
// Memanggil koneksi database sesuai instruksi
require_once __DIR__ . '/config/database.php';

// Header agar merespons sebagai JSON
header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array("success" => false, "message" => "Koneksi database gagal."));
    exit();
}

$q = isset($_GET["q"]) ? trim($_GET["q"]) : "";
$likeAjax = "%" . $q . "%";

// Query mencari supplier berdasarkan Kode, Nama, atau Singkatan
$sql = "
    SELECT TOP 20
        SUP_ID,
        ISNULL(SUP_CODE, '') AS SUP_CODE,
        ISNULL(SUP_ABBR, '') AS SUP_ABBR,
        ISNULL(SUP_COMP, '') AS SUP_COMP,
        ISNULL(CURR_CODE, '') AS CURR_CODE
    FROM dbo.SUPPLIER
    WHERE
        (? = ''
         OR ISNULL(SUP_CODE, '') LIKE ?
         OR ISNULL(SUP_COMP, '') LIKE ?
         OR ISNULL(SUP_ABBR, '') LIKE ?)
    ORDER BY SUP_CODE
";

$stmt = sqlsrv_query($conn, $sql, array($q, $likeAjax, $likeAjax, $likeAjax));

if ($stmt === false) {
    echo json_encode(array("success" => false, "message" => "Query gagal dijalankan."));
    exit();
}

$out = array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $out[] = array(
        "SUP_ID" => intval($r["SUP_ID"]),
        "SUP_CODE" => trim((string)$r["SUP_CODE"]),
        "SUP_ABBR" => trim((string)$r["SUP_ABBR"]),
        "SUP_COMP" => trim((string)$r["SUP_COMP"]),
        "CURR_CODE" => trim((string)$r["CURR_CODE"])
    );
}

// Kembalikan data dalam bentuk JSON
echo json_encode(array("success" => true, "rows" => $out));
exit();
?>