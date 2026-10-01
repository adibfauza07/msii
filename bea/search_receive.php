<?php
// Pastikan path ke koneksi database sudah benar
require_once __DIR__ . '/config/database.php';

// Header agar merespons secara spesifik sebagai JSON
header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array("success" => false, "message" => "Koneksi database gagal."));
    exit;
}

$qAjax = isset($_GET["q"]) ? trim($_GET["q"]) : "";
$likeAjax = "%" . $qAjax . "%";

// Ambil maksimal 10 data pencarian
$sql = "
    SELECT TOP 10 
        R.RCV_ID, ISNULL(R.RCV_NO, '') AS RCV_NO, ISNULL(R.RCV_DONO, '') AS RCV_DONO, ISNULL(S.SUP_COMP, '') AS SUP_COMP
    FROM dbo.RECEIVE R
    LEFT JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID
    WHERE (? = '' OR ISNULL(R.RCV_NO, '') LIKE ? OR ISNULL(R.RCV_DONO, '') LIKE ? OR ISNULL(S.SUP_COMP, '') LIKE ?)
    ORDER BY R.RCV_DATE DESC, R.RCV_NO DESC
";

$stmtSearch = sqlsrv_query($conn, $sql, array($qAjax, $likeAjax, $likeAjax, $likeAjax));

$out = array();
if ($stmtSearch !== false) {
    while ($rSearch = sqlsrv_fetch_array($stmtSearch, SQLSRV_FETCH_ASSOC)) {
        $out[] = array(
            "RCV_ID" => intval($rSearch["RCV_ID"]),
            "RCV_NO" => trim((string)$rSearch["RCV_NO"]),
            "RCV_DONO" => trim((string)$rSearch["RCV_DONO"]),
            "SUP_COMP" => trim((string)$rSearch["SUP_COMP"])
        );
    }
}

// Kembalikan data dalam format JSON
echo json_encode(array("success" => true, "rows" => $out));
exit;
?>