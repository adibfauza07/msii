<?php
require_once __DIR__ . "/../config/db_plant2.php";

header("Content-Type: application/json");

if ($conn === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Koneksi database gagal.",
        "rows" => array()
    ));
    exit();
}

function request_value($name) {
    return isset($_REQUEST[$name]) ? trim($_REQUEST[$name]) : "";
}

function get_field($row, $names, $default = "") {
    for ($i = 0; $i < count($names); $i++) {
        $name = $names[$i];

        if (isset($row[$name]) && $row[$name] !== null) {
            return $row[$name];
        }
    }

    return $default;
}

function normalize_date_for_sql($dateText) {
    $dateText = trim($dateText);

    if ($dateText == "") {
        return "";
    }

    if (strlen($dateText) == 10) {
        return $dateText . " 00:00:00";
    }

    return $dateText;
}

function get_item_by_price_id($conn, $price_id) {
    $sql = "
        SELECT TOP 1
            ISNULL(I.ITEM_CODE, '') AS ITEM_CODE,
            ISNULL(I.ITEM_NAME, '') AS ITEM_NAME
        FROM PRICE P
        LEFT JOIN ITEMS I
            ON I.ITEM_ID = P.PART_ID
        WHERE P.PRICE_ID = ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($price_id));

    if ($stmt === false) {
        return array(
            "ITEM_CODE" => "",
            "ITEM_NAME" => ""
        );
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$row) {
        return array(
            "ITEM_CODE" => "",
            "ITEM_NAME" => ""
        );
    }

    return array(
        "ITEM_CODE" => trim($row["ITEM_CODE"]),
        "ITEM_NAME" => trim($row["ITEM_NAME"])
    );
}

$cust_code  = request_value("CUST_CODE");
$start_date = normalize_date_for_sql(request_value("START_DATE"));
$end_date   = normalize_date_for_sql(request_value("END_DATE"));
$q          = request_value("q");

if ($cust_code == "" || $start_date == "" || $end_date == "") {
    echo json_encode(array(
        "success" => false,
        "message" => "Parameter kosong. CUST_CODE / START_DATE / END_DATE wajib ada.",
        "debug" => array(
            "CUST_CODE" => $cust_code,
            "START_DATE" => $start_date,
            "END_DATE" => $end_date,
            "q" => $q
        ),
        "rows" => array()
    ));
    exit();
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_DI_PART ?, ?, ?
";

$params = array(
    $cust_code,
    $start_date,
    $end_date
);

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Query SP_DI_PART gagal: " . print_r(sqlsrv_errors(), true),
        "debug" => array(
            "CUST_CODE" => $cust_code,
            "START_DATE" => $start_date,
            "END_DATE" => $end_date,
            "q" => $q,
            "sql" => $sql
        ),
        "rows" => array()
    ));
    exit();
}

$data = array();
$qUpper = strtoupper($q);

do {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

        $priceId = intval(get_field($row, array(
            "PRICE_ID",
            "price_id"
        ), 0));

        $code = trim(get_field($row, array(
            "CODE",
            "code",
            "ITEM_CODE",
            "item_code",
            "PART_CODE",
            "part_code"
        ), ""));

        $name = trim(get_field($row, array(
            "NAME",
            "name",
            "ITEM_NAME",
            "item_name",
            "PART_NAME",
            "part_name"
        ), ""));

        $dailySch = intval(get_field($row, array(
            "DAILY_SCH",
            "daily_sch",
            "DIPA_QTY",
            "dipa_qty",
            "QTY",
            "qty"
        ), 0));

        if ($priceId <= 0) {
            continue;
        }

        /*
            Kalau SP_DI_PART tidak mengeluarkan CODE,
            ambil CODE dari PRICE -> ITEMS.
        */
        if ($code == "" || $name == "") {
            $itemInfo = get_item_by_price_id($conn, $priceId);

            if ($code == "") {
                $code = $itemInfo["ITEM_CODE"];
            }

            if ($name == "") {
                $name = $itemInfo["ITEM_NAME"];
            }
        }

        if ($code == "" && $name == "") {
            continue;
        }

        if ($q != "") {
            $text = strtoupper($code . " " . $name);

            if (strpos($text, $qUpper) === false) {
                continue;
            }
        }

        $data[] = array(
            "PRICE_ID"  => $priceId,
            "CODE"      => $code,
            "NAME"      => $name,
            "DAILY_SCH" => $dailySch,
            "PACK_ID"   => 1,
            "PACK_DESC" => "BB",
            "DIPA_PQTY" => 1
        );

        if (count($data) >= 80) {
            break 2;
        }
    }
} while (sqlsrv_next_result($stmt));

echo json_encode(array(
    "success" => true,
    "message" => "OK",
    "debug" => array(
        "CUST_CODE" => $cust_code,
        "START_DATE" => $start_date,
        "END_DATE" => $end_date,
        "q" => $q,
        "total" => count($data)
    ),
    "rows" => $data
));
exit();
?>