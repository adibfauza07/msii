<?php

// ===============================
// KONEKSI DATABASE SESUAI USER LOGIN
// ===============================
require_once "../config/database_ppic.php";

// ===============================
// QUERY DATA
// ===============================
$sql = "SELECT id, part_code, part_no, part_name, qty_polibag, qty_box
        FROM data_barcode_showa
        ORDER BY id ASC";

$res = sqlsrv_query($conn, $sql);

$data = [];
while ($r = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
    $data[] = $r;
}

// ===============================
// OUTPUT JSON
// ===============================
header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);
