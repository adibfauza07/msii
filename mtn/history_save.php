<?php
// =======================================================
// SECURITY (ubah sesuai role yang berhak)
// =======================================================
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2', 'admin']);   // Maintenance + Admin boleh edit
require "../config/database.php";

// =======================================================
header("Content-Type: application/json");

// =======================================================
// VALIDASI INPUT
// =======================================================
$idmac = isset($_POST['idmac']) ? intval($_POST['idmac']) : 0;
$rows  = isset($_POST['rows']) ? $_POST['rows'] : [];

if ($idmac <= 0) {
    echo json_encode(['status'=>'error','message'=>'ID_MAC tidak valid']);
    exit;
}

// Convert dd-MMM-yyyy → yyyy-mm-dd
function toSQLdate($d) {
    if (!$d || trim($d) == "") return null;
    $ts = strtotime($d);
    return $ts ? date("Y-m-d", $ts) : null;
}

// =======================================================
// PROSES BARIS
// =======================================================
foreach ($rows as $r) {

    $id   = isset($r['id']) ? trim($r['id']) : "";
    $del  = isset($r['del']) ? intval($r['del']) : 0;

    $date = isset($r['date']) ? toSQLdate($r['date']) : null;
    $desc = isset($r['description']) ? trim($r['description']) : "";
    $srv  = isset($r['service']) ? trim($r['service']) : "";
    $ker  = isset($r['kerusakan']) ? trim($r['kerusakan']) : null;
    $kat  = isset($r['kategori']) ? trim($r['kategori']) : null;
    $tim  = isset($r['time']) ? trim($r['time']) : null;

    // Jika baris kosong, skip
    if ($id == "" && $date === null && $desc=="" && $srv=="" && $ker=="" && $kat=="" && $tim=="") {
        continue;
    }

    // ===================================================
    // DELETE
    // ===================================================
    if ($del == 1 && $id != "") {

        $sql = "DELETE FROM MTN_HISTORY_MAC WHERE ID = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);

        if ($stmt === false) {
            echo json_encode([
                'status'=>'error',
                'message'=>'SQL DELETE error',
                'sqlsrv'=>sqlsrv_errors()
            ]);
            exit;
        }

        continue;
    }

    // ===================================================
    // INSERT
    // ===================================================
    if ($id == "") {

        $sql = "INSERT INTO MTN_HISTORY_MAC
                (ID_MAC, DATE, DESCRIPTION, SERVICE, ID_KERUSAKAN, TIME, KATEGORI)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = sqlsrv_query($conn, $sql,
            [$idmac, $date, $desc, $srv, $ker, $tim, $kat]
        );

        if ($stmt === false) {
            echo json_encode([
                'status'=>'error',
                'message'=>'SQL INSERT error',
                'sqlsrv'=>sqlsrv_errors()
            ]);
            exit;
        }
    }

    // ===================================================
    // UPDATE
    // ===================================================
    else {

        $sql = "UPDATE MTN_HISTORY_MAC
                SET DATE = ?, DESCRIPTION = ?, SERVICE = ?,
                    ID_KERUSAKAN = ?, TIME = ?, KATEGORI = ?
                WHERE ID = ?";

        $stmt = sqlsrv_query($conn, $sql,
            [$date, $desc, $srv, $ker, $tim, $kat, $id]
        );

        if ($stmt === false) {
            echo json_encode([
                'status'=>'error',
                'message'=>'SQL UPDATE error',
                'sqlsrv'=>sqlsrv_errors()
            ]);
            exit;
        }
    }
} 

// =======================================================
// SUCCESS
// =======================================================
echo json_encode(['status'=>'ok']);
exit;

?>
