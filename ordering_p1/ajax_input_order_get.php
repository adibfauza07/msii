<?php
require_once __DIR__ . "/../config/database_ordering.php";

header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Koneksi database gagal.",
        "header"  => null,
        "details" => array()
    ));
    exit();
}

function json_error($msg) {
    echo json_encode(array(
        "success" => false,
        "message" => $msg,
        "header"  => null,
        "details" => array()
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

function safe_float($value) {
    if ($value === null || $value === "") {
        return 0;
    }

    return floatval($value);
}

function date_out($value) {
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

$ordr_id_raw = post_value("ORDR_ID");
$ordr_po     = post_value("ORDR_PO");
$ordr_date   = post_value("ORDR_DATE");

/*
    Aturan pencarian:
    1. Kalau ORDR_ID ada, pakai ORDR_ID.
    2. Kalau ORDR_ID kosong, pakai ORDER PO + ORDER DATE.
    3. Kalau ORDER DATE kosong, ambil PO terbaru.
       Catatan: PO boleh sama asal tanggal berbeda.
*/

$whereHeader  = "";
$paramsHeader = array();

if ($ordr_id_raw !== "") {
    if (!is_numeric($ordr_id_raw)) {
        json_error("ORDR_ID tidak valid: " . $ordr_id_raw);
    }

    $whereHeader = "O.ORDR_ID = ?";
    $paramsHeader[] = intval($ordr_id_raw);
} else {
    if ($ordr_po == "") {
        json_error("ORDER PO belum diisi.");
    }

    if ($ordr_date != "") {
        $whereHeader = "
            O.ORDR_PO = ?
            AND CONVERT(date, O.ORDR_DATE) = CONVERT(date, ?)
        ";

        $paramsHeader[] = $ordr_po;
        $paramsHeader[] = $ordr_date;
    } else {
        /*
            Fallback kalau date tidak dikirim.
            Karena PO bisa sama beda tanggal, sistem ambil yang paling baru.
        */
        $whereHeader = "O.ORDR_PO = ?";
        $paramsHeader[] = $ordr_po;
    }
}

/*
    HEADER ORDER
*/
$sqlHeader = "
    SET NOCOUNT ON;

    SELECT TOP 1
        O.ORDR_ID,
        O.CUST_ID,
        ISNULL(O.CUST_CODE, '') AS ORDER_CUST_CODE,
        ISNULL(O.ORDR_PO, '') AS ORDR_PO,
        O.ORDR_DATE,
        ISNULL(O.ORDR_REM, '') AS ORDR_REM,
        ISNULL(O.ORDR_STAT, '') AS ORDR_STAT,
        ISNULL(O.ORDR_CURR, '') AS ORDR_CURR,
        ISNULL(O.ORDR_PENDING, 0) AS ORDR_PENDING,
        ISNULL(O.ORDR_CLOSE, 0) AS ORDR_CLOSE,
        ISNULL(O.ORDR_REPLACEMENT, 0) AS ORDR_REPLACEMENT,
        ISNULL(C.CUST_CODE, '') AS CUST_CODE,
        ISNULL(C.CUST_COMP, '') AS CUST_COMP,
        ISNULL(C.CURR_CODE, '') AS CUST_CURR
    FROM dbo.ORDERS AS O
    INNER JOIN dbo.CUST AS C
        ON O.CUST_ID = C.CUST_ID
    WHERE $whereHeader
    ORDER BY
        O.ORDR_DATE DESC,
        O.ORDR_ID DESC
";

$stmtHeader = sqlsrv_query($conn, $sqlHeader, $paramsHeader);

if ($stmtHeader === false) {
    json_error("Query header order gagal: " . print_r(sqlsrv_errors(), true));
}

$h = sqlsrv_fetch_array($stmtHeader, SQLSRV_FETCH_ASSOC);

if (!$h) {
    if ($ordr_date != "") {
        json_error("Order tidak ditemukan untuk PO dan tanggal tersebut.");
    }

    json_error("Order tidak ditemukan.");
}

$ordr_id = safe_int($h["ORDR_ID"]);

/*
    DETAIL ORDER
    Harga:
    - Utama pakai ORDR_PAR.ORDP_PRICE
    - Kalau kosong/null, ambil PRICE_DETAIL.PRDT_PRICE sesuai tanggal order.
*/
$sqlDetail = "
    SET NOCOUNT ON;

    SELECT
        OP.ORDR_ID,
        OP.ORDP_LINO,
        OP.PRICE_ID,

        ISNULL(OP.ORDP_PRICE, ISNULL(PD.PRDT_PRICE, 0)) AS ORDP_PRICE,

        ISNULL(OP.ORDP_QTY, 0)  AS ORDP_QTY,
        ISNULL(OP.ORDP_DQTY, 0) AS ORDP_DQTY,
        ISNULL(OP.ORDP_BQTY, 0) AS ORDP_BQTY,

        ISNULL(OP.ORDP_REM, '') AS ORDP_REM,
        ISNULL(OP.ORDP_CLOSE, 0) AS ORDP_CLOSE,

        ISNULL(I.ITEM_CODE, '') AS PART_CODE,
        ISNULL(I.ITEM_NAME, '') AS PART_NAME,
        ISNULL(I.ITEM_NO, '') AS PART_NUM,

        PD.PRDT_START,
        PD.PRDT_END,
        ISNULL(PD.PRDT_PRICE, 0) AS PRDT_PRICE
    FROM dbo.ORDR_PAR AS OP
    INNER JOIN dbo.ORDERS AS O
        ON OP.ORDR_ID = O.ORDR_ID
    INNER JOIN dbo.PRICE AS P
        ON OP.PRICE_ID = P.PRICE_ID
    INNER JOIN dbo.ITEMS AS I
        ON P.PART_ID = I.ITEM_ID
    OUTER APPLY
    (
        SELECT TOP 1
            PRDT_PRICE,
            PRDT_START,
            PRDT_END
        FROM dbo.PRICE_DETAIL AS PD2
        WHERE PD2.PRICE_ID = OP.PRICE_ID
          AND O.ORDR_DATE BETWEEN PD2.PRDT_START AND PD2.PRDT_END
        ORDER BY PD2.PRDT_START DESC
    ) AS PD
    WHERE OP.ORDR_ID = ?
    ORDER BY OP.ORDP_LINO
";

$stmtDetail = sqlsrv_query($conn, $sqlDetail, array($ordr_id));

if ($stmtDetail === false) {
    json_error("Query detail order gagal: " . print_r(sqlsrv_errors(), true));
}

$details = array();

while ($d = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
    $qty   = safe_int($d["ORDP_QTY"]);
    $dqty  = safe_int($d["ORDP_DQTY"]);
    $bqty  = safe_int($d["ORDP_BQTY"]);
    $price = safe_float($d["ORDP_PRICE"]);

    /*
        Jika balance kosong / belum benar, hitung ulang dari Qty - Delivery.
    */
    if ($bqty == 0 && $qty > 0 && $dqty == 0) {
        $bqty = $qty;
    }

    $partName = safe_trim($d["PART_NAME"]);
    $partNum  = safe_trim($d["PART_NUM"]);

    if ($partNum != "" && strpos($partName, $partNum) === false) {
        $partName = trim($partName . " " . $partNum);
    }

    $details[] = array(
        "ORDR_ID"    => safe_int($d["ORDR_ID"]),
        "ORDP_LINO"  => safe_int($d["ORDP_LINO"]),
        "PRICE_ID"   => safe_int($d["PRICE_ID"]),

        "PART_CODE"  => safe_trim($d["PART_CODE"]),
        "PART_NAME"  => $partName,
        "PART_NUM"   => $partNum,

        "ORDP_PRICE" => number_format($price, 4, ".", ""),
        "ORDP_QTY"   => $qty,
        "ORDP_DQTY"  => $dqty,
        "ORDP_BQTY"  => $bqty,
        "ORDP_REM"   => safe_trim($d["ORDP_REM"]),
        "ORDP_CLOSE" => safe_int($d["ORDP_CLOSE"]),

        "PRDT_PRICE" => number_format(safe_float($d["PRDT_PRICE"]), 4, ".", ""),
        "PRDT_START" => date_out($d["PRDT_START"]),
        "PRDT_END"   => date_out($d["PRDT_END"]),

        "AMOUNT"     => number_format($price * $qty, 2, ".", "")
    );
}

/*
    RESPONSE
*/
$curr = safe_trim($h["ORDR_CURR"]);

if ($curr == "") {
    $curr = safe_trim($h["CUST_CURR"]);
}

echo json_encode(array(
    "success" => true,
    "message" => "Data order ditemukan.",
    "header" => array(
        "ORDR_ID"           => $ordr_id,
        "CUST_ID"           => safe_int($h["CUST_ID"]),
        "CUST_CODE"         => safe_trim($h["CUST_CODE"]),
        "CUST_COMP"         => safe_trim($h["CUST_COMP"]),

        "ORDR_PO"           => safe_trim($h["ORDR_PO"]),
        "ORDR_DATE"         => date_out($h["ORDR_DATE"]),
        "ORDR_REM"          => safe_trim($h["ORDR_REM"]),
        "ORDR_STAT"         => safe_trim($h["ORDR_STAT"]),
        "ORDR_CURR"         => $curr,
        "ORDR_PENDING"      => safe_int($h["ORDR_PENDING"]),
        "ORDR_CLOSE"        => safe_int($h["ORDR_CLOSE"]),
        "ORDR_REPLACEMENT"  => safe_int($h["ORDR_REPLACEMENT"])
    ),
    "details" => $details
));
?>