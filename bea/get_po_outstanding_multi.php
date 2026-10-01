<?php
// ============================================================
// get_po_outstanding_multi.php
// Outstanding PO gabungan P1/P2
// PHP 5.4 + SQL Server sqlsrv
// Receive yang dihitung: RECEIVE.RCV_TYPE = '01'
// ============================================================

ob_start();
set_time_limit(180);

if (session_id() === '') {
    session_start();
}

require_once dirname(__DIR__) . "/config/global.php";

function json_output($payload)
{
    if (ob_get_length()) {
        ob_clean();
    }

    header("Content-Type: application/json; charset=UTF-8");
    header("Cache-Control: no-cache, no-store, must-revalidate");

    echo json_encode($payload);
    exit;
}

function sql_error_text()
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);

    if (!is_array($errors) || count($errors) === 0) {
        return "Kesalahan SQL Server tidak diketahui.";
    }

    $messages = array();

    foreach ($errors as $error) {
        $state = isset($error["SQLSTATE"]) ? $error["SQLSTATE"] : "";
        $code = isset($error["code"]) ? $error["code"] : "";
        $message = isset($error["message"]) ? $error["message"] : "";

        $messages[] = trim("[" . $state . "] " . $code . " " . $message);
    }

    return implode(" | ", $messages);
}

function normalize_plant($value)
{
    $value = strtolower(trim((string)$value));

    if (!in_array($value, array("all", "p1", "p2"), true)) {
        return "all";
    }

    return $value;
}

function date_json($value)
{
    if ($value === null || $value === "") {
        return "";
    }

    if ($value instanceof DateTime) {
        return $value->format("Y-m-d");
    }

    $timestamp = strtotime((string)$value);

    return $timestamp !== false ? date("Y-m-d", $timestamp) : (string)$value;
}

function sortable_value($value)
{
    if ($value === null) {
        return "";
    }

    if ($value instanceof DateTime) {
        return $value->format("Y-m-d H:i:s.u");
    }

    if (is_scalar($value)) {
        return (string)$value;
    }

    return "";
}

if (
    !isset($_SESSION["db_user"]) ||
    trim((string)$_SESSION["db_user"]) === ""
) {
    json_output(array(
        "status" => "error",
        "message" => "Session login database tidak tersedia.",
        "data" => array(),
        "errors" => array()
    ));
}

$plant = isset($_POST["plant"])
    ? normalize_plant($_POST["plant"])
    : "all";

$startDate = isset($_POST["start_date"])
    ? trim($_POST["start_date"])
    : "";

$endDate = isset($_POST["end_date"])
    ? trim($_POST["end_date"])
    : "";

$supplierCode = isset($_POST["sup_code"])
    ? trim($_POST["sup_code"])
    : "";

if (
    !preg_match("/^\d{4}-\d{2}-\d{2}$/", $startDate) ||
    !preg_match("/^\d{4}-\d{2}-\d{2}$/", $endDate)
) {
    json_output(array(
        "status" => "error",
        "message" => "Format tanggal harus YYYY-MM-DD.",
        "data" => array(),
        "errors" => array()
    ));
}

$dbName = "msData";
$uid = $_SESSION["db_user"];
$pwd = isset($_SESSION["db_pass"]) ? $_SESSION["db_pass"] : "";

$servers = array(
    "p1" => array(
        "ip" => "192.168.0.4",
        "label" => "Plant 1",
        "short" => "P1"
    ),
    "p2" => array(
        "ip" => "192.168.0.9",
        "label" => "Plant 2",
        "short" => "P2"
    )
);

$serverKeys = $plant === "all"
    ? array("p1", "p2")
    : array($plant);

$supplierParam = $supplierCode === ""
    ? "%"
    : "%" . $supplierCode . "%";

$rows = array();
$errors = array();
$serverStatus = array("p1" => false, "p2" => false);

foreach ($serverKeys as $serverKey) {
    $connectionOptions = array(
        "Database" => $dbName,
        "Uid" => $uid,
        "PWD" => $pwd,
        "CharacterSet" => "UTF-8",
        "LoginTimeout" => 5,
        "ReturnDatesAsStrings" => false
    );

    $conn = @sqlsrv_connect(
        $servers[$serverKey]["ip"],
        $connectionOptions
    );

    if ($conn === false) {
        $errors[] =
            $servers[$serverKey]["label"] .
            ": koneksi gagal. " .
            sql_error_text();

        continue;
    }

    $serverStatus[$serverKey] = true;

    $sql = "
        WITH PO_AGG AS
        (
            SELECT
                PO_ID,
                ITEM_ID,
                SUM(ISNULL(POD_QTY, 0)) AS PO_QTY,
                MAX(POD_UNIT) AS ITEM_UNIT
            FROM dbo.PO_DETAIL
            GROUP BY PO_ID, ITEM_ID
        ),
        RECEIVE_AGG AS
        (
            SELECT
                RD.PO_ID,
                RD.ITEM_ID,
                SUM(ISNULL(RD.RCVD_QTY, 0)) AS RECEIVE_QTY
            FROM dbo.RECEIVE_DETAIL AS RD
            INNER JOIN dbo.RECEIVE AS R
                ON RD.RCV_ID = R.RCV_ID
               AND R.RCV_TYPE = '01'
            GROUP BY RD.PO_ID, RD.ITEM_ID
        )
        SELECT
            P.PO_NUM,
            P.PO_DATE,
            I.ITEM_CODE,
            I.ITEM_NAME,
            PA.ITEM_UNIT,
            P.PO_CUR,
            S.SUP_CODE,
            S.SUP_COMP,
            PA.PO_QTY,
            ISNULL(RA.RECEIVE_QTY, 0) AS RECEIVE_QTY,
            PA.PO_QTY - ISNULL(RA.RECEIVE_QTY, 0) AS SISA_QTY
        FROM PO_AGG AS PA
        INNER JOIN dbo.PO AS P
            ON PA.PO_ID = P.PO_ID
        INNER JOIN dbo.ITEMS AS I
            ON PA.ITEM_ID = I.ITEM_ID
        INNER JOIN dbo.SUPPLIER AS S
            ON P.SUP_ID = S.SUP_ID
        LEFT OUTER JOIN RECEIVE_AGG AS RA
            ON PA.PO_ID = RA.PO_ID
           AND PA.ITEM_ID = RA.ITEM_ID
        WHERE
            P.PO_DATE >= ?
            AND P.PO_DATE < DATEADD(DAY, 1, ?)
            AND S.SUP_CODE LIKE ?
            AND PA.PO_QTY - ISNULL(RA.RECEIVE_QTY, 0) > 0
        ORDER BY
            P.PO_DATE,
            P.PO_NUM,
            I.ITEM_CODE
    ";

    $stmt = @sqlsrv_query(
        $conn,
        $sql,
        array($startDate, $endDate, $supplierParam)
    );

    if ($stmt === false) {
        $errors[] =
            $servers[$serverKey]["label"] .
            ": query gagal. " .
            sql_error_text();

        sqlsrv_close($conn);
        continue;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            "PLANT_KEY" => $serverKey,
            "PLANT_SHORT" => $servers[$serverKey]["short"],
            "PLANT_LABEL" => $servers[$serverKey]["label"],
            "PO_NUM" => isset($row["PO_NUM"]) ? trim((string)$row["PO_NUM"]) : "",
            "PO_DATE_SORT" => isset($row["PO_DATE"]) ? sortable_value($row["PO_DATE"]) : "",
            "PO_DATE" => isset($row["PO_DATE"]) ? date_json($row["PO_DATE"]) : "",
            "ITEM_CODE" => isset($row["ITEM_CODE"]) ? trim((string)$row["ITEM_CODE"]) : "",
            "ITEM_NAME" => isset($row["ITEM_NAME"]) ? trim((string)$row["ITEM_NAME"]) : "",
            "ITEM_UNIT" => isset($row["ITEM_UNIT"]) ? trim((string)$row["ITEM_UNIT"]) : "",
            "PO_CUR" => isset($row["PO_CUR"]) ? trim((string)$row["PO_CUR"]) : "",
            "SUP_CODE" => isset($row["SUP_CODE"]) ? trim((string)$row["SUP_CODE"]) : "",
            "SUP_COMP" => isset($row["SUP_COMP"]) ? trim((string)$row["SUP_COMP"]) : "",
            "PO_QTY" => isset($row["PO_QTY"]) ? (float)$row["PO_QTY"] : 0,
            "RECEIVE_QTY" => isset($row["RECEIVE_QTY"]) ? (float)$row["RECEIVE_QTY"] : 0,
            "SISA_QTY" => isset($row["SISA_QTY"]) ? (float)$row["SISA_QTY"] : 0
        );
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
}

usort($rows, function ($left, $right) {
    $fields = array("PLANT_SHORT", "PO_DATE_SORT", "PO_NUM", "ITEM_CODE");

    foreach ($fields as $field) {
        $leftValue = isset($left[$field])
            ? sortable_value($left[$field])
            : "";

        $rightValue = isset($right[$field])
            ? sortable_value($right[$field])
            : "";

        $compare = strcmp($leftValue, $rightValue);

        if ($compare !== 0) {
            return $compare;
        }
    }

    return 0;
});

foreach ($rows as $index => $row) {
    unset($rows[$index]["PO_DATE_SORT"]);
}

json_output(array(
    "status" => "success",
    "message" => "",
    "data" => array_values($rows),
    "errors" => $errors,
    "servers" => $serverStatus,
    "plant" => $plant
));
