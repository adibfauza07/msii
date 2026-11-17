<?php
// ============================================================
// get_po_performance_yearly.php
// Chart Performance PO vs Receive per Bulan (Jan-Des)
// PHP 5.4 + SQL Server sqlsrv
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

if (!isset($_SESSION["db_user"]) || trim((string)$_SESSION["db_user"]) === "") {
    json_output(array(
        "status" => "error",
        "message" => "Session login database tidak tersedia.",
        "data" => array()
    ));
}

$plant = isset($_POST["plant"]) ? strtolower(trim((string)$_POST["plant"])) : "all";
$year = isset($_POST["year"]) ? (int)$_POST["year"] : (int)date("Y");
$supplierCode = isset($_POST["sup_code"]) ? trim($_POST["sup_code"]) : "";

if (!in_array($plant, array("all", "p1", "p2"), true)) {
    $plant = "all";
}

$dbName = "msData";
$uid = $_SESSION["db_user"];
$pwd = isset($_SESSION["db_pass"]) ? $_SESSION["db_pass"] : "";

$servers = array(
    "p1" => array("ip" => "192.168.0.4", "label" => "Plant 1", "short" => "P1"),
    "p2" => array("ip" => "192.168.0.9", "label" => "Plant 2", "short" => "P2")
);

$serverKeys = ($plant === "all") ? array("p1", "p2") : array($plant);
$supplierParam = ($supplierCode === "") ? "%" : $supplierCode;

// Siapkan array dasar untuk 12 bulan (Januari - Desember)
$monthlyData = array();
for ($i = 1; $i <= 12; $i++) {
    $monthlyData[$i] = array(
        "month" => $i,
        "po_qty" => 0,
        "recv_qty" => 0
    );
}

$errors = array();

foreach ($serverKeys as $serverKey) {
    $connectionOptions = array(
        "Database" => $dbName,
        "Uid" => $uid,
        "PWD" => $pwd,
        "CharacterSet" => "UTF-8",
        "LoginTimeout" => 5
    );

    $conn = @sqlsrv_connect($servers[$serverKey]["ip"], $connectionOptions);

    if ($conn === false) {
        $errors[] = $servers[$serverKey]["label"] . " koneksi gagal.";
        continue;
    }

    // Query untuk mengelompokkan jumlah PO dan Receive per bulan
    $sql = "
        WITH PO_AGG AS (
            SELECT 
                PO_ID, 
                SUM(ISNULL(POD_QTY, 0)) AS PO_QTY
            FROM dbo.PO_DETAIL
            GROUP BY PO_ID
        ),
        RECEIVE_AGG AS (
            SELECT 
                RD.PO_ID, 
                SUM(ISNULL(RD.RCVD_QTY, 0)) AS RECEIVE_QTY
            FROM dbo.RECEIVE_DETAIL AS RD
            INNER JOIN dbo.RECEIVE AS R
                ON RD.RCV_ID = R.RCV_ID
            
            GROUP BY RD.PO_ID
        )
        SELECT 
            MONTH(P.PO_DATE) AS [month],
            SUM(PA.PO_QTY) AS total_po,
            SUM(ISNULL(RA.RECEIVE_QTY, 0)) AS total_recv
        FROM PO_AGG AS PA
        INNER JOIN dbo.PO AS P
            ON PA.PO_ID = P.PO_ID
        INNER JOIN dbo.SUPPLIER AS S
            ON P.SUP_ID = S.SUP_ID
        LEFT OUTER JOIN RECEIVE_AGG AS RA
            ON PA.PO_ID = RA.PO_ID
        WHERE 
            YEAR(P.PO_DATE) = ?
            AND S.SUP_CODE LIKE ?
        GROUP BY 
            MONTH(P.PO_DATE)
    ";

    $stmt = @sqlsrv_query($conn, $sql, array($year, $supplierParam));

    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $m = (int)$row['month'];
            if (isset($monthlyData[$m])) {
                $monthlyData[$m]['po_qty'] += (float)$row['total_po'];
                $monthlyData[$m]['recv_qty'] += (float)$row['total_recv'];
            }
        }
        sqlsrv_free_stmt($stmt);
    } else {
        $errors[] = $servers[$serverKey]["label"] . " query gagal.";
    }

    sqlsrv_close($conn);
}

// Return data array ke Javascript dalam format JSON
json_output(array(
    "status" => "success",
    "message" => "",
    "data" => array_values($monthlyData),
    "errors" => $errors
));