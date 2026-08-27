<?php
// ============================================================
// search_sup_outstanding_multi.php
// Autocomplete supplier gabungan P1/P2
// PHP 5.4 + SQL Server sqlsrv
// ============================================================

ob_start();

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

function normalize_plant($value)
{
    $value = strtolower(trim((string)$value));

    if (!in_array($value, array("all", "p1", "p2"), true)) {
        return "all";
    }

    return $value;
}

$query = isset($_POST["q"]) ? trim($_POST["q"]) : "";
$plant = isset($_POST["plant"])
    ? normalize_plant($_POST["plant"])
    : "all";

if (
    $query === "" ||
    !isset($_SESSION["db_user"]) ||
    trim((string)$_SESSION["db_user"]) === ""
) {
    json_output(array());
}

$dbName = "msData";
$uid = $_SESSION["db_user"];
$pwd = isset($_SESSION["db_pass"]) ? $_SESSION["db_pass"] : "";

$servers = array(
    "p1" => array("ip" => "192.168.0.4", "short" => "P1"),
    "p2" => array("ip" => "192.168.0.9", "short" => "P2")
);

$serverKeys = $plant === "all"
    ? array("p1", "p2")
    : array($plant);

$like = "%" . $query . "%";
$startLike = $query . "%";
$records = array();

foreach ($serverKeys as $serverKey) {
    $connectionOptions = array(
        "Database" => $dbName,
        "Uid" => $uid,
        "PWD" => $pwd,
        "CharacterSet" => "UTF-8",
        "LoginTimeout" => 5
    );

    $conn = @sqlsrv_connect(
        $servers[$serverKey]["ip"],
        $connectionOptions
    );

    if ($conn === false) {
        continue;
    }

    $sql = "
        SELECT TOP 20
            SUP_CODE,
            SUP_COMP
        FROM dbo.SUPPLIER
        WHERE
            SUP_CODE LIKE ?
            OR SUP_COMP LIKE ?
        ORDER BY
            CASE
                WHEN SUP_CODE LIKE ? THEN 0
                ELSE 1
            END,
            SUP_CODE
    ";

    $stmt = @sqlsrv_query(
        $conn,
        $sql,
        array($like, $like, $startLike)
    );

    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $supplierCode = isset($row["SUP_CODE"])
                ? trim((string)$row["SUP_CODE"])
                : "";

            $supplierName = isset($row["SUP_COMP"])
                ? trim((string)$row["SUP_COMP"])
                : "";

            if ($supplierCode === "") {
                continue;
            }

            $key = $supplierCode . "|" . $supplierName;

            if (!isset($records[$key])) {
                $records[$key] = array(
                    "SUP_CODE" => $supplierCode,
                    "SUP_COMP" => $supplierName,
                    "plants" => array()
                );
            }

            $records[$key]["plants"][
                $servers[$serverKey]["short"]
            ] = true;
        }

        sqlsrv_free_stmt($stmt);
    }

    sqlsrv_close($conn);
}

$result = array();

foreach ($records as $record) {
    $plants = array_keys($record["plants"]);
    sort($plants);

    $result[] = array(
        "SUP_CODE" => $record["SUP_CODE"],
        "SUP_COMP" => $record["SUP_COMP"],
        "PLANT_SHORT" => implode("/", $plants)
    );
}

usort($result, function ($left, $right) {
    return strcmp($left["SUP_CODE"], $right["SUP_CODE"]);
});

json_output(array_slice($result, 0, 30));
