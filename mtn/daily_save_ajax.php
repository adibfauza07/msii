<?php
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2','admin']);  

require "../config/database.php";
header("Content-Type: application/json");

// ===============================================
// VALIDASI METHOD
// ===============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('status'=>'error','message'=>'Invalid request'));
    exit;
}

// ambil POST style PHP 5.6
$mac_id = isset($_POST['mac_id']) ? intval($_POST['mac_id']) : 0;
$mac    = isset($_POST['mac'])    ? trim($_POST['mac'])      : '';
$plant  = isset($_POST['plant'])  ? trim($_POST['plant'])    : '';
$rows   = isset($_POST['rows'])   ? $_POST['rows']           : array();

if ($mac_id <= 0){
    echo json_encode(array('status'=>'error','message'=>'MAC_ID kosong'));
    exit;
}

// ===============================================
// KONVERSI TANGGAL dd-MMM-yy -> yyyy-mm-dd
// ===============================================
function toSQLdate($d){
    if (!$d || trim($d)=="") return null;

    $dt = DateTime::createFromFormat("d-M-y", $d);
    if ($dt) return $dt->format("Y-m-d");

    // fallback
    $t = strtotime($d);
    return $t ? date("Y-m-d", $t) : null;
}

$newIds = array();

// ===============================================
// LOOP ROWS
// ===============================================
foreach($rows as $idx => $r){

    $id      = isset($r['id'])      ? trim($r['id'])      : '';
    $dateStr = isset($r['date'])    ? trim($r['date'])    : '';
    $prob    = isset($r['problem']) ? trim($r['problem']) : '';
    $cause   = isset($r['cause'])   ? trim($r['cause'])   : '';
    $pic     = isset($r['pic'])     ? trim($r['pic'])     : '';
    $fromh   = isset($r['fromh'])   ? trim($r['fromh'])   : '';
    $toh     = isset($r['toh'])     ? trim($r['toh'])     : '';
    $finish  = isset($r['finish'])  ? trim($r['finish'])  : '';
    $status  = isset($r['status'])  ? trim($r['status'])  : '';
    $remark  = isset($r['remark'])  ? trim($r['remark'])  : '';

    // skip jika kosong semua
    if ($id=='' && $dateStr=='' && $prob=='' && $cause=='' &&
        $pic=='' && $fromh=='' && $toh=='' && $finish=='' &&
        $status=='' && $remark=='') {
        continue;
    }

    $dateSql = toSQLdate($dateStr);

    $fromSql = $fromh   ? $fromh.":00"  : null;
    $toSql   = $toh     ? $toh.":00"    : null;
    $finSql  = $finish  ? $finish.":00" : null;

    // ====================================================
    // INSERT
    // ====================================================
    if ($id === "") {

        $sql = "
            INSERT INTO MTN_DAILY
                (MAC_ID, MAC, PLANT, DATE,
                 PROBLEM, CAUSE, PIC,
                 FROM_HOURS, TO_HOURS, FINISH,
                 STATUS, DESCRIPTION)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ";

        $params = array(
            $mac_id,
            $mac,
            $plant,
            $dateSql,
            $prob,
            $cause,
            $pic,
            $fromSql,
            $toSql,
            $finSql,
            $status,
            $remark
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if (!$stmt){
            echo json_encode(array(
                'status'=>'error',
                'message'=>'SQL Insert Error',
                'sqlsrv'=>sqlsrv_errors()
            ));
            exit;
        }

        // ambil ID baru
        $res = sqlsrv_query($conn,"SELECT CAST(SCOPE_IDENTITY() AS INT) AS ID");
        $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        $newIds[$idx] = $row['ID'];
    }

    // ====================================================
    // UPDATE
    // ====================================================
    else {

        $sql = "
            UPDATE MTN_DAILY SET
            DATE=?, PROBLEM=?, CAUSE=?, PIC=?,
            FROM_HOURS=?, TO_HOURS=?, FINISH=?,
            STATUS=?, DESCRIPTION=?
            WHERE ID=?
        ";

        $params = array(
            $dateSql,
            $prob,
            $cause,
            $pic,
            $fromSql,
            $toSql,
            $finSql,
            $status,
            $remark,
            $id
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if (!$stmt){
            echo json_encode(array(
                'status'=>'error',
                'message'=>'SQL Update Error',
                'sqlsrv'=>sqlsrv_errors()
            ));
            exit;
        }
    }
}

// ===============================================
// SUCCESS
// ===============================================
echo json_encode(array('status'=>'ok','new_ids'=>$newIds));
exit;
?>
