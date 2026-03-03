<?php
// ======================================================================
// SECURITY - HANYA MTN YANG BOLEH AKSES (sesuaikan jika perlu)
// ======================================================================
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2']);     // jika ingin role lain, ganti misal: only(['mtn','admin']);
require_once "../config/database.php";

// ======================================================================
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Invalid request']);
    exit;
}

$mac_id     = isset($_POST['mac_id'])     ? intval($_POST['mac_id']) : 0;
$mac        = isset($_POST['mac'])        ? trim($_POST['mac'])      : '';
$mac_serial = isset($_POST['mac_serial']) ? trim($_POST['mac_serial']) : '';
$mac_type   = isset($_POST['mac_type'])   ? trim($_POST['mac_type'])   : '';
$plant      = isset($_POST['plant'])      ? trim($_POST['plant'])      : '';
$tonage     = isset($_POST['tonage'])     ? trim($_POST['tonage'])     : '';
$mac_active = isset($_POST['mac_active']) ? 1 : 0;

if ($mac === '') {
    echo json_encode(['status'=>'error','message'=>'Field MAC wajib diisi.']);
    exit;
}

$plantVal  = ($plant  === '') ? null : (int)$plant;
$tonageVal = ($tonage === '') ? null : (int)$tonage;

// ======================================================================
// INSERT MODE
// ======================================================================
if ($mac_id <= 0) {

    $sql = "INSERT INTO MAC_MTN
            (MAC, MAC_SERIAL, MAC_TYPE, MAC_ACTIVE, PLANT, TONAGE)
            OUTPUT INSERTED.MAC_ID
            VALUES (?, ?, ?, ?, ?, ?)";

    $params = [
        $mac,
        $mac_serial,
        $mac_type,
        $mac_active,
        $plantVal,
        $tonageVal
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo json_encode([
            'status'=>'error',
            'message'=>'SQL Error (insert)',
            'sqlsrv'=>sqlsrv_errors()
        ]);
        exit;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $newId = $row ? (int)$row['MAC_ID'] : 0;

    echo json_encode(['status'=>'ok','new_id'=>$newId]);
    exit;
}

// ======================================================================
// UPDATE MODE
// ======================================================================
else {

    $sql = "UPDATE MAC_MTN
            SET MAC = ?,
                MAC_SERIAL = ?,
                MAC_TYPE = ?,
                MAC_ACTIVE = ?,
                PLANT = ?,
                TONAGE = ?
            WHERE MAC_ID = ?";

    $params = [
        $mac,
        $mac_serial,
        $mac_type,
        $mac_active,
        $plantVal,
        $tonageVal,
        $mac_id
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo json_encode([
            'status'=>'error',
            'message'=>'SQL Error (update)',
            'sqlsrv'=>sqlsrv_errors()
        ]);
        exit;
    }

    echo json_encode(['status'=>'ok','new_id'=>0]);
    exit;
}
?>
