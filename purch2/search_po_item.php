<?php
require_once dirname(__DIR__) . "/config/db_plant2.php";

header("Content-Type: application/json; charset=UTF-8");

if (!isset($conn) || $conn === false) {
    echo json_encode(array());
    exit;
}

$q = isset($_POST["q"]) ? trim($_POST["q"]) : "";
$supId = isset($_POST["sup_id"]) ? intval($_POST["sup_id"]) : 0;

if ($q === "" || $supId <= 0) {
    echo json_encode(array());
    exit;
}

$like = "%" . $q . "%";
$startLike = $q . "%";

/* ==============================================================
   Query Sesuai dengan Qry_Dropdown_ITEM di UReceive.dfm 
   Difilter O/S > 0 dan PO_CLOSE = 0
============================================================== */
$sql = "
    SELECT TOP 30 
        I.ITEM_ID, 
        I.ITEM_CODE, 
        I.ITEM_NAME, 
        P.PO_NUM, 
        P.PO_ID,
        PD.POD_PRICE,
        (PD.POD_QTY - ISNULL(SUM(RD.RCVD_QTY), 0)) AS OS_QTY
    FROM dbo.PO AS P
    INNER JOIN dbo.PO_DETAIL AS PD ON PD.PO_ID = P.PO_ID
    INNER JOIN dbo.ITEMS AS I ON I.ITEM_ID = PD.ITEM_ID
    LEFT JOIN dbo.RECEIVE_DETAIL AS RD ON RD.PO_ID = P.PO_ID AND RD.ITEM_ID = PD.ITEM_ID
    WHERE P.SUP_ID = ? AND P.PO_CLOSE = 0
      AND (I.ITEM_CODE LIKE ? OR I.ITEM_NAME LIKE ? OR P.PO_NUM LIKE ?)
    GROUP BY 
        P.PO_NUM, P.PO_ID, P.SUP_ID, PD.POD_QTY, 
        I.ITEM_CODE, I.ITEM_ID, I.ITEM_NAME, PD.POD_PRICE
    HAVING (PD.POD_QTY - ISNULL(SUM(RD.RCVD_QTY), 0)) > 0
    ORDER BY
        CASE WHEN I.ITEM_CODE LIKE ? THEN 0 ELSE 1 END,
        I.ITEM_CODE
";

$params = array($supId, $like, $like, $like, $startLike);
$stmt = @sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array());
    exit;
}

$data = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = array(
        "ITEM_ID"   => intval($row["ITEM_ID"]),
        "PO_ID"     => intval($row["PO_ID"]),
        "ITEM_CODE" => trim((string) $row["ITEM_CODE"]),
        "ITEM_NAME" => trim((string) $row["ITEM_NAME"]),
        "PO_NUM"    => trim((string) $row["PO_NUM"]),
        "OS_QTY"    => floatval($row["OS_QTY"]),
        "POD_PRICE" => floatval($row["POD_PRICE"])
    );
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

echo json_encode($data);