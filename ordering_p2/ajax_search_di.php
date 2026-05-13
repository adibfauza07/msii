<?php
require_once __DIR__ . "/../config/db_plant2.php";

header("Content-Type: application/json");

if ($conn === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Koneksi database gagal."
    ));
    exit();
}

function json_error($msg) {
    echo json_encode(array(
        "success" => false,
        "message" => $msg
    ));
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

function date_input($value) {
    if ($value instanceof DateTime) {
        return $value->format("Y-m-d");
    }

    if ($value == "" || $value === null) {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("Y-m-d", $ts);
}

$search_type = post_value("search_type");
$keyword     = post_value("keyword");

if ($keyword == "") {
    json_error("Keyword pencarian belum diisi.");
}

if ($search_type != "DI_INVNO") {
    $search_type = "DI_NO";
}

$like = "%" . $keyword . "%";

if ($search_type == "DI_INVNO") {
    $where = "D.DI_INVNO LIKE ?";
} else {
    $where = "D.DI_NO LIKE ?";
}


// ==========================================================
// CARI HEADER DI
// ==========================================================
$sqlHeader = "
    SELECT TOP 1
        D.DI_ID,
        D.DI_NO,
        D.CUST_ID,
        D.CUST_CODE,
        D.DI_START_DATE,
        D.DI_DATE,
        D.DI_INVNO,
        D.DI_DSNO,
        D.DI_ORDERNO,
        C.CUST_COMP,
        C.CUST_ABBR
    FROM DI D
    LEFT JOIN CUST C ON C.CUST_ID = D.CUST_ID
    WHERE $where
    ORDER BY D.DI_ID DESC
";

$stmtHeader = sqlsrv_query($conn, $sqlHeader, array($like));

if ($stmtHeader === false) {
    json_error("Query header DI gagal: " . print_r(sqlsrv_errors(), true));
}

$h = sqlsrv_fetch_array($stmtHeader, SQLSRV_FETCH_ASSOC);

if (!$h) {
    json_error("Data DI tidak ditemukan.");
}

$di_id = intval($h["DI_ID"]);


// ==========================================================
// CARI DETAIL DI_PART
// Catatan:
// Jangan pakai PRICE.ITEM_NAME karena kolom itu tidak ada.
// NAME sementara diisi sama dengan PART_CODE.
// PACK_DESC diambil dari DI_PART.DIPA_PACK atau PACK.PACK_CODE.
// ==========================================================
$sqlDetail = "
    SELECT
        DP.DIPA_LINO,

        ISNULL(I.ITEM_CODE, ISNULL(DP.PART_CODE, '')) AS CODE,
        ISNULL(I.ITEM_NAME, '') AS NAME,

        ISNULL(DP.DIPA_QTY, 0) AS DIPA_QTY,
        ISNULL(DP.DIPA_PQTY, 0) AS DIPA_PQTY,

        CASE
            WHEN ISNULL(DP.DIPA_PACK, '') <> '' THEN DP.DIPA_PACK
            ELSE ISNULL(PK.PACK_CODE, '')
        END AS PACK_DESC,

        ISNULL(DP.LOCATION, '') AS LOCATION,
        DP.PRICE_ID,
        DP.PACK_ID
    FROM DI_PART DP
    LEFT JOIN PRICE P ON DP.PRICE_ID = P.PRICE_ID
    LEFT JOIN ITEMS I ON P.PART_ID = I.ITEM_ID
    LEFT JOIN PACK PK ON PK.PACK_ID = DP.PACK_ID
    WHERE DP.DI_ID = ?
    ORDER BY DP.DIPA_LINO
";
$stmtDetail = sqlsrv_query($conn, $sqlDetail, array($di_id));

if ($stmtDetail === false) {
    json_error("Query detail DI_PART gagal: " . print_r(sqlsrv_errors(), true));
}

$details = array();

while ($d = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
    $details[] = array(
        "DIPA_LINO" => intval($d["DIPA_LINO"]),
        "CODE"      => trim($d["CODE"]),
        "NAME"      => trim($d["NAME"]),
        "DIPA_QTY"  => intval($d["DIPA_QTY"]),
        "DIPA_PQTY" => intval($d["DIPA_PQTY"]),
        "PACK_DESC" => trim($d["PACK_DESC"]),
        "LOCATION"  => trim($d["LOCATION"]),
        "PRICE_ID"  => intval($d["PRICE_ID"]),
        "PACK_ID"   => intval($d["PACK_ID"])
    );
}


// ==========================================================
// RESPONSE
// ==========================================================
echo json_encode(array(
    "success" => true,
    "message" => "Data DI berhasil ditemukan.",
    "header" => array(
        "DI_ID"          => $di_id,
        "DI_NO"          => trim($h["DI_NO"]),
        "CUST_ID"        => intval($h["CUST_ID"]),
        "CUST_CODE"      => trim($h["CUST_CODE"]),
        "CUST_COMP"      => trim($h["CUST_COMP"]),
        "CUST_ABBR"      => trim($h["CUST_ABBR"]),
        "DI_START_DATE"  => date_input($h["DI_START_DATE"]),
        "DI_DATE"        => date_input($h["DI_DATE"]),
        "DI_INVNO"       => trim($h["DI_INVNO"]),
        "DI_DSNO"        => trim($h["DI_DSNO"]),
        "DI_ORDERNO"     => trim($h["DI_ORDERNO"])
    ),
    "details" => $details
));
?>