<?php
// ajax_get_customer_parts.php
ob_start();
require_once __DIR__ . "/../config/database_ordering.php"; 
ob_clean();

header("Content-Type: application/json; charset=utf-8");

if (!isset($conn) || $conn === false) {
    echo json_encode(array("status" => false, "message" => "Gagal koneksi ke database."));
    exit();
}

$custCode = isset($_POST["cust_code"]) ? trim($_POST["cust_code"]) : "";
$tglAwal  = isset($_POST["tgl_awal"]) ? trim($_POST["tgl_awal"]) : "";
$tglAkhir = isset($_POST["tgl_akhir"]) ? trim($_POST["tgl_akhir"]) : "";

if ($custCode === "" || empty($tglAwal) || empty($tglAkhir)) {
    echo json_encode(array("status" => false, "message" => "Customer Code dan Rentang Tanggal wajib diisi."));
    exit();
}

// Format filter rentang tanggal
$tglAwalFull  = $tglAwal . " 00:00:00";
$tglAkhirFull = $tglAkhir . " 23:59:59.997";

$sql = "
    SELECT     
        DI_PART.DI_ID,
        DI_PART.DIPA_LINO,
        DI_PART.PART_CODE,
        DI_PART.DIPA_QTY,
        DI_PART.DIPA_PQTY,
        DI.CUST_ID, 
        CONVERT(varchar(10), DI.DI_DATE, 120) AS DI_DATE, 
        CUST.CUST_CODE, 
        CUST.CUST_COMP, 
        DI_PART.PACK_ID, 
        PACK.PACK_CODE, 
        PACK.PACK_NAME,
        STD_PACK.STD_PACK_BOX,
        ITEMS.ITEM_CODE, -- Tambahan ITEM_CODE
        ITEMS.ITEM_NAME  -- Tambahan ITEM_NAME
    FROM DI 
    INNER JOIN CUST ON DI.CUST_ID = CUST.CUST_ID 
    INNER JOIN DI_PART ON DI.DI_ID = DI_PART.DI_ID 
    LEFT JOIN PACK ON DI_PART.PACK_ID = PACK.PACK_ID
    LEFT JOIN PRICE ON DI_PART.PRICE_ID = PRICE.PRICE_ID 
    LEFT JOIN ITEMS ON PRICE.PART_ID = ITEMS.ITEM_ID 
    LEFT JOIN STD_PACK ON ITEMS.ITEM_CODE = STD_PACK.ITEM_CODE
    WHERE CUST.CUST_CODE = ?
      AND DI.DI_DATE >= ?
      AND DI.DI_DATE <= ?
    ORDER BY DI.DI_DATE ASC, DI_PART.DI_ID ASC, DI_PART.DIPA_LINO ASC
";

$params = array($custCode, $tglAwalFull, $tglAkhirFull);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    $errMsg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Unknown Error';
    echo json_encode(array("status" => false, "message" => "Error SQL: " . $errMsg));
    exit();
}

$items = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $items[] = array(
        "DI_DATE"      => $row["DI_DATE"],
        "PART_CODE"    => isset($row["PART_CODE"]) ? trim($row["PART_CODE"]) : "",
        "ITEM_CODE"    => isset($row["ITEM_CODE"]) ? trim($row["ITEM_CODE"]) : "", // Mengambil ITEM_CODE
        "ITEM_NAME"    => isset($row["ITEM_NAME"]) ? trim($row["ITEM_NAME"]) : "", // Mengambil ITEM_NAME
        "DIPA_QTY"     => (float)$row["DIPA_QTY"], 
        "DIPA_PQTY"    => isset($row["DIPA_PQTY"]) ? (float)$row["DIPA_PQTY"] : 0, 
        "PACK_ID"      => $row["PACK_ID"] !== null ? (int)$row["PACK_ID"] : null,
        "PACK_CODE"    => isset($row["PACK_CODE"]) ? trim($row["PACK_CODE"]) : "",
        "PACK_NAME"    => isset($row["PACK_NAME"]) ? trim($row["PACK_NAME"]) : "",
        "STD_PACK_BOX" => isset($row["STD_PACK_BOX"]) ? (float)$row["STD_PACK_BOX"] : 0
    );
}

echo json_encode(array(
    "status" => true,
    "total"  => count($items),
    "data"   => $items
));
exit();
?>