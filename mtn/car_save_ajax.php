<?php
require_once "../mtn/middleware/Auth.php";
require_once "../mtn/middleware/RoleCheck.php";
only(['p2']);   
require_once "../config/database.php";

header("Content-Type: application/json");

// ===============================
// Validasi method
// ===============================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('status'=>'error','message'=>'Invalid request'));
    exit;
}

// ===============================
// Ambil POST (PHP 5.6 Compatible)
// ===============================
$mac_id = isset($_POST['mac_id']) ? intval($_POST['mac_id']) : 0;
$mac    = isset($_POST['mac'])    ? trim($_POST['mac'])      : '';
$plant  = isset($_POST['plant'])  ? trim($_POST['plant'])    : '';
$rows   = isset($_POST['rows'])   ? $_POST['rows']           : array();

if ($mac_id <= 0) {
    echo json_encode(array('status'=>'error','message'=>'MAC_ID kosong'));
    exit;
}

// ===============================
// Helper tanggal
// ===============================
function toSQLdate($d){
    if (!$d || trim($d) == "") return null;

    $dt = DateTime::createFromFormat("d-M-y", $d);
    if ($dt) return $dt->format("Y-m-d");

    $t = strtotime($d);
    return $t ? date("Y-m-d", $t) : null;
}

$results = array();

// ===============================
// Loop Row untuk INSERT/UPDATE
// ===============================
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
    if (
        $carno == "" && $carno_old == "" &&
        $issue_date == null && $problem == "" && $dept_code == "" &&
        $effective == null && $pic == "" && $closed == 0 &&
        $tgl_kembali == null && $tanda_kembali == 0
    ) {
        continue;
    }

    // ==============================
    // INSERT BARU
    // ==============================
    if ($carno_old == "") {

        $sql = "
            INSERT INTO MTN_HISTORY_CARNO
                (ID_MAC, MAC, PLANT, CARNO, ISSUE_DATE,
                PROBLEM, DEPT_CODE, EFFECTIVE_DATE,
                PIC, CLOSED, TANGGAL_PENGEMBALIAN, TANDA_PENGEMBALIAN)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $params = array(
            $mac_id,
            $mac,
            $plant,
            $carno,
            $issue_date,
            $problem,
            $dept_code,
            $effective,
            $pic,
            $closed,
            $tgl_kembali,
            $tanda_kembali
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if (!$stmt) {
            echo json_encode(array(
                'status'=>'error',
                'message'=>'Insert error',
                'sqlsrv'=>sqlsrv_errors()
            ));
            exit;
        }

        $results[$idx] = "insert";

    }
    // ==============================
    // UPDATE
    // ==============================
    else {

        $sql = "
            UPDATE MTN_HISTORY_CARNO SET
                CARNO = ?,
                ISSUE_DATE = ?,
                PROBLEM = ?,
                DEPT_CODE = ?,
                EFFECTIVE_DATE = ?,
                PIC = ?,
                CLOSED = ?,
                TANGGAL_PENGEMBALIAN = ?,
                TANDA_PENGEMBALIAN = ?
            WHERE ID_MAC = ? AND CARNO = ?
        ";

        $params = array(
            $carno,
            $issue_date,
            $problem,
            $dept_code,
            $effective,
            $pic,
            $closed,
            $tgl_kembali,
            $tanda_kembali,
            $mac_id,
            $carno_old
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if (!$stmt) {
            echo json_encode(array(
                'status'=>'error',
                'message'=>'Update error',
                'sqlsrv'=>sqlsrv_errors()
            ));
            exit;
        }

        $results[$idx] = "update";
    }
}

// ===============================
// SUCCESS
// ===============================
echo json_encode(array(
    'status'=>'ok',
    'rows'=>$results
));
exit;
?>
