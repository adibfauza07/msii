<?php
// ======================================================================
// SECURITY MIDDLEWARE (WAJIB ADA DI SEMUA API)
// ======================================================================
require_once "../middleware/Auth.php";       // harus login
require_once "../middleware/RoleCheck.php";  // cek role
only(['p1', 'p2']);                           // hanya untuk PPIC (Plant1 & Plant2)

// ======================================================================
// KONEKSI DATABASE SESUAI USER LOGIN
// ======================================================================
require_once "../config/database.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ambil parameter aksi
$action = isset($_GET['action']) ? $_GET['action'] : '';

/* ======================================================
   DELETE DATA
======================================================*/
if ($action == "delete") {

    if (!isset($_GET['id']) || $_GET['id'] == "") {
        echo json_encode(["status" => "error", "msg" => "ID kosong"]);
        exit;
    }

    $id = intval($_GET['id']);

    $sql = "DELETE FROM data_barcode_showa WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);

    if ($stmt === false) {
        echo json_encode(["status" => "error", "msg" => sqlsrv_errors()]);
        exit;
    }

    echo json_encode(["status" => "success"]);
    exit;
}

/* ======================================================
   SAVE_ALL (UPDATE MASSAL)
======================================================*/
if ($action == "save_all") {

    $json = file_get_contents("php://input");
    $rows = json_decode($json, true);

    foreach ($rows as $r) {

        if (!isset($r["id"]) || $r["id"] == "") continue;

        $sql = "UPDATE data_barcode_showa SET
                part_code   = ?,
                part_no     = ?,
                part_name   = ?,
                qty_polibag = ?,
                qty_box     = ?
            WHERE id = ?";

        sqlsrv_query($conn, $sql, [
            $r["part_code"],
            $r["part_no"],
            $r["part_name"],
            intval($r["qty_polibag"]),
            intval($r["qty_box"]),
            intval($r["id"]),
        ]);
    }

    echo "OK";
    exit;
}

/* ======================================================
   SAVE ONE (AUTOSAVE)
======================================================*/
if ($action == "save") {

    $json = file_get_contents("php://input");
    $r = json_decode($json, true);

    if (!isset($r["id"]) || $r["id"] == "") {
        echo "NO-ID";
        exit;
    }

    $sql = "UPDATE data_barcode_showa SET
            part_code   = ?,
            part_no     = ?,
            part_name   = ?,
            qty_polibag = ?,
            qty_box     = ?
        WHERE id = ?";

    sqlsrv_query($conn, $sql, [
        $r["part_code"],
        $r["part_no"],
        $r["part_name"],
        intval($r["qty_polibag"]),
        intval($r["qty_box"]),
        intval($r["id"]),
    ]);

    echo "UPDATED";
    exit;
}

// Jika action tidak ada
echo json_encode(["status" => "error", "msg" => "Invalid action"]);
exit;
?>
