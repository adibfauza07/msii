<?php
require_once "../mtn/middleware/Auth.php";
require_once "../mtn/middleware/RoleCheck.php";
only(['p2']);   
require_once "../config/database.php";

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error', 'message'=>'Invalid request']);
    exit;
}

$mac_id = isset($_POST['mac_id']) ? intval($_POST['mac_id']) : 0;
$mac    = isset($_POST['mac'])    ? trim($_POST['mac'])      : '';
$plant  = isset($_POST['plant'])  ? trim($_POST['plant'])    : '';
$rows   = isset($_POST['rows'])   ? $_POST['rows']           : [];

if ($mac_id <= 0) {
    echo json_encode(['status'=>'error', 'message'=>'MAC_ID tidak valid (Kosong)']);
    exit;
}

function toSQLdate($d){
    if (!$d || trim($d) == "") return null;
    $dt = DateTime::createFromFormat("d-M-y", $d);
    if ($dt) return $dt->format("Y-m-d");
    $t = strtotime($d);
    return $t ? date("Y-m-d", $t) : null;
}

// ==========================================================
// MULAI DATABASE TRANSACTION (Mencegah data setengah tersimpan)
// ==========================================================
if (sqlsrv_begin_transaction($conn) === false) {
    echo json_encode(['status'=>'error', 'message'=>'Gagal memulai transaksi database']);
    exit;
}

$results = [];

foreach ($rows as $idx => $r){
    $carno_old = isset($r['carno_old']) ? trim($r['carno_old']) : '';
    $carno     = isset($r['carno'])     ? trim($r['carno'])     : '';
    $issue_date = toSQLdate(isset($r['issue_date']) ? $r['issue_date'] : '');
    $problem    = isset($r['problem']) ? trim($r['problem']) : '';
    $dept_code  = isset($r['dept_code']) ? trim($r['dept_code']) : '';
    $effective  = toSQLdate(isset($r['effective_date']) ? $r['effective_date'] : '');
    $pic        = isset($r['pic']) ? trim($r['pic']) : '';
    $closed     = isset($r['closed']) ? 1 : 0;
    $tanda_kembali = isset($r['tanda_pengembalian']) ? 1 : 0;
    $tgl_kembali = toSQLdate(isset($r['tgl_pengembalian']) ? $r['tgl_pengembalian'] : '');

    // Skip total baris kosong
    if (empty($carno) && empty($carno_old) && !$issue_date && empty($problem) && 
        empty($dept_code) && !$effective && empty($pic) && $closed == 0 && 
        !$tgl_kembali && $tanda_kembali == 0) {
        continue;
    }

    if ($carno_old == "") {
        // INSERT
        $sql = "INSERT INTO MTN_HISTORY_CARNO 
                (ID_MAC, MAC, PLANT, CARNO, ISSUE_DATE, PROBLEM, DEPT_CODE, EFFECTIVE_DATE, PIC, CLOSED, TANGGAL_PENGEMBALIAN, TANDA_PENGEMBALIAN)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [$mac_id, $mac, $plant, $carno, $issue_date, $problem, $dept_code, $effective, $pic, $closed, $tgl_kembali, $tanda_kembali];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if (!$stmt) {
            sqlsrv_rollback($conn); // Rollback jika error
            echo json_encode(['status'=>'error', 'message'=>'Gagal Insert pada baris ke-'.($idx+1), 'sqlsrv'=>sqlsrv_errors()]);
            exit;
        }
        $results[$idx] = "insert";
    } else {
        // UPDATE
        $sql = "UPDATE MTN_HISTORY_CARNO SET 
                CARNO = ?, ISSUE_DATE = ?, PROBLEM = ?, DEPT_CODE = ?, EFFECTIVE_DATE = ?, PIC = ?, CLOSED = ?, TANGGAL_PENGEMBALIAN = ?, TANDA_PENGEMBALIAN = ?
                WHERE ID_MAC = ? AND CARNO = ?";
        $params = [$carno, $issue_date, $problem, $dept_code, $effective, $pic, $closed, $tgl_kembali, $tanda_kembali, $mac_id, $carno_old];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if (!$stmt) {
            sqlsrv_rollback($conn); // Rollback jika error
            echo json_encode(['status'=>'error', 'message'=>'Gagal Update pada CARNO: '.$carno_old, 'sqlsrv'=>sqlsrv_errors()]);
            exit;
        }
        $results[$idx] = "update";
    }
}

// COMMIT JIKA SEMUA BERHASIL
sqlsrv_commit($conn);

echo json_encode(['status'=>'ok', 'rows'=>$results]);
exit;
?>