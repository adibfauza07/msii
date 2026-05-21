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

function safe_trim($value) {
    if ($value === null) {
        return "";
    }

    return trim((string)$value);
}

function safe_int($value) {
    if ($value === null || $value === "") {
        return 0;
    }

    return intval($value);
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

/*
    CARI HEADER DI

    Perbaikan utama:
    - CUST_CODE jangan hanya ambil dari DI.CUST_CODE.
    - Kalau DI.CUST_CODE kosong, ambil dari tabel CUST berdasarkan CUST_ID.
    - Tambah fallback join CUST berdasarkan DI.CUST_CODE.
*/
$sqlHeader = "
    SELECT TOP 1
        D.DI_ID,
        D.DI_NO,

        COALESCE(NULLIF(D.CUST_ID, 0), C1.CUST_ID, C2.CUST_ID, 0) AS CUST_ID,

        COALESCE(
            NULLIF(LTRIM(RTRIM(D.CUST_CODE)), ''),
            NULLIF(LTRIM(RTRIM(C1.CUST_CODE)), ''),
            NULLIF(LTRIM(RTRIM(C2.CUST_CODE)), ''),
            ''
        ) AS CUST_CODE,

        COALESCE(
            NULLIF(LTRIM(RTRIM(C1.CUST_COMP)), ''),
            NULLIF(LTRIM(RTRIM(C2.CUST_COMP)), ''),
            ''
        ) AS CUST_COMP,

        COALESCE(
            NULLIF(LTRIM(RTRIM(C1.CUST_ABBR)), ''),
            NULLIF(LTRIM(RTRIM(C2.CUST_ABBR)), ''),
            ''
        ) AS CUST_ABBR,

        D.DI_START_DATE,
        D.DI_DATE,
        ISNULL(D.DI_INVNO, '') AS DI_INVNO,
        ISNULL(D.DI_DSNO, '') AS DI_DSNO,
        ISNULL(D.DI_ORDERNO, '') AS DI_ORDERNO

    FROM DI D

    LEFT JOIN CUST C1
        ON C1.CUST_ID = D.CUST_ID

    LEFT JOIN CUST C2
        ON C2.CUST_CODE = D.CUST_CODE

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

$di_id = safe_int($h["DI_ID"]);

/*
    CARI DETAIL DI_PART

    CODE / NAME diambil dari PRICE -> ITEMS.
    PACK_DESC diambil dari DI_PART.DIPA_PACK, kalau kosong ambil PACK.PACK_CODE.
*/
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
        ISNULL(DP.PRICE_ID, 0) AS PRICE_ID,
        ISNULL(DP.PACK_ID, 0) AS PACK_ID

    FROM DI_PART DP

    LEFT JOIN PRICE P
        ON DP.PRICE_ID = P.PRICE_ID

    LEFT JOIN ITEMS I
        ON P.PART_ID = I.ITEM_ID

    LEFT JOIN PACK PK
        ON PK.PACK_ID = DP.PACK_ID

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
        "DIPA_LINO" => safe_int($d["DIPA_LINO"]),
        "CODE"      => safe_trim($d["CODE"]),
        "NAME"      => safe_trim($d["NAME"]),
        "DIPA_QTY"  => safe_int($d["DIPA_QTY"]),
        "DIPA_PQTY" => safe_int($d["DIPA_PQTY"]),
        "PACK_DESC" => safe_trim($d["PACK_DESC"]),
        "LOCATION"  => safe_trim($d["LOCATION"]),
        "PRICE_ID"  => safe_int($d["PRICE_ID"]),
        "PACK_ID"   => safe_int($d["PACK_ID"])
    );
}

/*
    RESPONSE
*/
echo json_encode(array(
    "success" => true,
    "message" => "Data DI berhasil ditemukan.",
    "header" => array(
        "DI_ID"          => $di_id,
        "DI_NO"          => safe_trim($h["DI_NO"]),

        "CUST_ID"        => safe_int($h["CUST_ID"]),
        "CUST_CODE"      => safe_trim($h["CUST_CODE"]),
        "CUST_COMP"      => safe_trim($h["CUST_COMP"]),
        "CUST_ABBR"      => safe_trim($h["CUST_ABBR"]),

        "DI_START_DATE"  => date_input($h["DI_START_DATE"]),
        "DI_DATE"        => date_input($h["DI_DATE"]),
        "DI_INVNO"       => safe_trim($h["DI_INVNO"]),
        "DI_DSNO"        => safe_trim($h["DI_DSNO"]),
        "DI_ORDERNO"     => safe_trim($h["DI_ORDERNO"])
    ),
    "details" => $details
));
?>