<?php
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2']);     // ubah jika role lain boleh hapus
require "../config/database.php";
header('Content-Type: application/json');

// ======================================================================
// VALIDASI REQUEST
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Invalid request']);
    exit;
}

$idmac = isset($_POST['idmac']) ? intval($_POST['idmac']) : 0;
$rows  = isset($_POST['rows'])  ? $_POST['rows'] : [];

if ($idmac <= 0) {
    echo json_encode(['status'=>'error','message'=>'ID MAC kosong']);
    exit;
}

// ======================================================================
// Convert dd-MMM-yyyy → yyyy-mm-dd
// ======================================================================
function toSQLdate($d){
    if (!$d || trim($d)=="") return null;
    $t = strtotime($d);
    return $t ? date("Y-m-d", $t) : null;
}

$newIds = [];

// ======================================================================
// LOOP ROWS
// ======================================================================
foreach ($rows as $idx => $r){

    $id   = isset($r['id']) ? trim($r['id']) : '';
    $date = isset($r['date']) ? toSQLdate($r['date']) : null;
    $desc = isset($r['description']) ? trim($r['description']) : '';
    $serv = isset($r['service']) ? trim($r['service']) : '';
    $ker  = isset($r['kerusakan']) ? trim($r['kerusakan']) : '';
    $kat  = isset($r['kategori']) ? trim($r['kategori']) : '';
    $time = isset($r['time']) ? trim($r['time']) : '';

    if ($ker === '') $ker = null;
    if ($kat === '') $kat = null;
    if ($time === '') $time = null;

    // Skip row kosong
    if ($id==='' && $date===null && $desc==='' && $serv==='' &&
        $ker===null && $kat===null && $time===null) {
        continue;
    }

    // ======================================================================
    // INSERT BARU
    // ======================================================================
    if ($id === '') {

        $sql = "
            INSERT INTO MTN_HISTORY_MAC
            (ID_MAC, DATE, DESCRIPTION, SERVICE, ID_KERUSAKAN, TIME, KATEGORI)
            VALUES (?, ?, ?, ?, ?, ?, ?);
            SELECT SCOPE_IDENTITY() AS NEWID;
        ";

        $stmt = sqlsrv_query($conn, $sql, [$idmac, $date, $desc, $serv, $ker, $time, $kat]);

        if ($stmt === false) {
            echo json_encode([
                'status'=>'error',
                'message'=>'SQL Insert Error',
                'sqlsrv'=>sqlsrv_errors()
            ]);
            exit;
        }

        // Ambil NEWID
        sqlsrv_next_result($stmt);
        $rowNew = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $newIds[$idx] = $rowNew['NEWID'];
    }

    // ======================================================================
    // UPDATE
    // ======================================================================
    else {

        $sql = "UPDATE MTN_HISTORY_MAC
                SET DATE = ?, DESCRIPTION = ?, SERVICE = ?,
                    ID_KERUSAKAN = ?, TIME = ?, KATEGORI = ?
                WHERE ID = ?";

        $stmt = sqlsrv_query($conn, $sql, [$date, $desc, $serv, $ker, $time, $kat, $id]);

        if ($stmt === false) {
            echo json_encode([
                'status'=>'error',
                'message'=>'SQL Update Error',
                'sqlsrv'=>sqlsrv_errors()
            ]);
            exit;
        }
    }
}

// ======================================================================
// SUCCESS
// ======================================================================
echo json_encode(['status'=>'ok','new_ids'=>$newIds]);
exit;
?>
