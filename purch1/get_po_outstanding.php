<?php
require_once dirname(__DIR__) . "/config/global.php";

header("Content-Type: application/json");

if ($conn === false) {
    echo json_encode(array("data" => array(), "error" => "Database connection failed"));
    exit();
}

 $start_date = isset($_POST["start_date"]) ? trim($_POST["start_date"]) : "";
 $end_date   = isset($_POST["end_date"]) ? trim($_POST["end_date"]) : "";
 $sup_code   = isset($_POST["sup_code"]) ? trim($_POST["sup_code"]) : "";

if ($start_date === "" || $end_date === "") {
    echo json_encode(array("data" => array()));
    exit();
}

/* ── Query berdasarkan sp_POOutstandingItem, ditambah filter supplier opsional ── */
 $sql = "
    SELECT
        P.PO_NUM,
        P.PO_DATE,
        S.SUP_CODE,
        S.SUP_COMP,
        I.ITEM_CODE,
        I.ITEM_NAME,
        I.ITEM_UNIT,
        PD.POD_QTY AS PO_QTY,
        ISNULL(SUM(RD.RCVD_QTY), 0) AS RECEIVE_QTY,
        (PD.POD_QTY - ISNULL(SUM(RD.RCVD_QTY), 0)) AS SISA_QTY,
        P.PO_CUR
    FROM dbo.PO P
    INNER JOIN dbo.PO_DETAIL PD ON P.PO_ID = PD.PO_ID
    INNER JOIN dbo.ITEMS I      ON PD.ITEM_ID = I.ITEM_ID
    INNER JOIN dbo.SUPPLIER S   ON P.SUP_ID = S.SUP_ID
    LEFT  JOIN dbo.RECEIVE_DETAIL RD ON RD.PO_ID = P.PO_ID AND RD.ITEM_ID = PD.ITEM_ID
    LEFT  JOIN dbo.RECEIVE R        ON R.RCV_ID = RD.RCV_ID AND R.RCV_TYPE = '1'
    WHERE P.PO_DATE >= ?
      AND P.PO_DATE < DATEADD(DAY, 1, ?)
";

 $params = array($start_date, $end_date);

if ($sup_code !== "") {
    $sql .= " AND S.SUP_CODE = ?";
    $params[] = $sup_code;
}

 $sql .= "
    GROUP BY
        P.PO_NUM, P.PO_DATE, S.SUP_CODE, S.SUP_COMP,
        I.ITEM_CODE, I.ITEM_NAME, I.ITEM_UNIT,
        PD.POD_QTY, P.PO_CUR
    HAVING (PD.POD_QTY - ISNULL(SUM(RD.RCVD_QTY), 0)) > 0
    ORDER BY P.PO_DATE, P.PO_NUM, I.ITEM_CODE
";

 $stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array("data" => array(), "error" => "Query failed"));
    exit();
}

 $data = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

    $po_date = $row["PO_DATE"];
    if (is_object($po_date)) {
        $po_date = $po_date->format("d/m/Y");
    } else {
        $po_date = date("d/m/Y", strtotime($po_date));
    }

    $data[] = array(
        "PO_NUM"      => trim($row["PO_NUM"]),
        "PO_DATE"     => $po_date,
        "SUP_CODE"    => trim($row["SUP_CODE"]),
        "SUP_COMP"    => trim($row["SUP_COMP"]),
        "ITEM_CODE"   => trim($row["ITEM_CODE"]),
        "ITEM_NAME"   => trim($row["ITEM_NAME"]),
        "ITEM_UNIT"   => trim($row["ITEM_UNIT"]),
        "PO_QTY"      => floatval($row["PO_QTY"]),
        "RECEIVE_QTY" => floatval($row["RECEIVE_QTY"]),
        "SISA_QTY"    => floatval($row["SISA_QTY"]),
        "PO_CUR"      => trim($row["PO_CUR"])
    );
}

echo json_encode(array("data" => $data));