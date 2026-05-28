<?php
if (session_id() == "") {
    session_start();
}

/*
    API SAVE MASTER DATA SHOWA
    Semua response wajib JSON.
*/

require_once dirname(__DIR__) . "/config/db_plant2.php";

error_reporting(E_ALL);
ini_set("display_errors", 0);

header("Content-Type: application/json; charset=utf-8");

function send_json($arr) {
    echo json_encode($arr);
    exit;
}

function clean_text($value) {
    if ($value === null) {
        return "";
    }

    return trim((string)$value);
}

function clean_int($value) {
    if ($value === null || $value === "") {
        return 0;
    }

    return intval($value);
}

if ($conn === false) {
    send_json(array(
        "status" => "error",
        "message" => "Koneksi database gagal.",
        "detail" => sqlsrv_errors()
    ));
}

$action = isset($_GET["action"]) ? trim($_GET["action"]) : "";

/* ======================================================
   DELETE DATA
====================================================== */
if ($action == "delete") {
    $id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

    if ($id <= 0) {
        send_json(array(
            "status" => "error",
            "message" => "ID kosong / tidak valid."
        ));
    }

    $sql = "
        DELETE FROM data_barcode_showa
        WHERE id = ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($id));

    if ($stmt === false) {
        send_json(array(
            "status" => "error",
            "message" => "Gagal hapus data.",
            "detail" => sqlsrv_errors()
        ));
    }

    send_json(array(
        "status" => "success",
        "message" => "Data berhasil dihapus."
    ));
}

/* ======================================================
   SAVE_ALL
   - Jika ID ada  : UPDATE
   - Jika ID kosong: INSERT
====================================================== */
if ($action == "save_all") {
    $json = file_get_contents("php://input");
    $rows = json_decode($json, true);

    if (!is_array($rows)) {
        send_json(array(
            "status" => "error",
            "message" => "JSON tidak valid.",
            "raw" => $json
        ));
    }

    if (count($rows) == 0) {
        send_json(array(
            "status" => "error",
            "message" => "Tidak ada data untuk disimpan."
        ));
    }

    sqlsrv_begin_transaction($conn);

    $insertCount = 0;
    $updateCount = 0;
    $skipCount   = 0;

    foreach ($rows as $r) {
        $id          = isset($r["id"]) ? intval($r["id"]) : 0;
        $part_code   = isset($r["part_code"]) ? clean_text($r["part_code"]) : "";
        $part_no     = isset($r["part_no"]) ? clean_text($r["part_no"]) : "";
        $part_name   = isset($r["part_name"]) ? clean_text($r["part_name"]) : "";
        $qty_polibag = isset($r["qty_polibag"]) ? clean_int($r["qty_polibag"]) : 0;
        $qty_box     = isset($r["qty_box"]) ? clean_int($r["qty_box"]) : 0;

        /*
            Lewati baris kosong total.
        */
        if ($part_code == "" && $part_no == "" && $part_name == "") {
            $skipCount++;
            continue;
        }

        if ($id > 0) {
            $sql = "
                UPDATE data_barcode_showa
                SET
                    part_code   = ?,
                    part_no     = ?,
                    part_name   = ?,
                    qty_polibag = ?,
                    qty_box     = ?
                WHERE id = ?
            ";

            $params = array(
                $part_code,
                $part_no,
                $part_name,
                $qty_polibag,
                $qty_box,
                $id
            );

            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt === false) {
                sqlsrv_rollback($conn);

                send_json(array(
                    "status" => "error",
                    "message" => "Gagal update data ID: " . $id,
                    "detail" => sqlsrv_errors()
                ));
            }

            $updateCount++;
        } else {
            $sql = "
                INSERT INTO data_barcode_showa
                    (part_code, part_no, part_name, qty_polibag, qty_box)
                VALUES
                    (?, ?, ?, ?, ?)
            ";

            $params = array(
                $part_code,
                $part_no,
                $part_name,
                $qty_polibag,
                $qty_box
            );

            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt === false) {
                sqlsrv_rollback($conn);

                send_json(array(
                    "status" => "error",
                    "message" => "Gagal insert data baru.",
                    "detail" => sqlsrv_errors()
                ));
            }

            $insertCount++;
        }
    }

    sqlsrv_commit($conn);

    send_json(array(
        "status" => "success",
        "message" => "Data berhasil disimpan. Insert: " . $insertCount . ", Update: " . $updateCount . ", Skip kosong: " . $skipCount,
        "insert" => $insertCount,
        "update" => $updateCount,
        "skip"   => $skipCount
    ));
}

/* ======================================================
   SAVE ONE
====================================================== */
if ($action == "save") {
    $json = file_get_contents("php://input");
    $r = json_decode($json, true);

    if (!is_array($r)) {
        send_json(array(
            "status" => "error",
            "message" => "JSON tidak valid.",
            "raw" => $json
        ));
    }

    $id          = isset($r["id"]) ? intval($r["id"]) : 0;
    $part_code   = isset($r["part_code"]) ? clean_text($r["part_code"]) : "";
    $part_no     = isset($r["part_no"]) ? clean_text($r["part_no"]) : "";
    $part_name   = isset($r["part_name"]) ? clean_text($r["part_name"]) : "";
    $qty_polibag = isset($r["qty_polibag"]) ? clean_int($r["qty_polibag"]) : 0;
    $qty_box     = isset($r["qty_box"]) ? clean_int($r["qty_box"]) : 0;

    if ($part_code == "" && $part_no == "" && $part_name == "") {
        send_json(array(
            "status" => "error",
            "message" => "Part Code / Part No / Part Name masih kosong."
        ));
    }

    if ($id > 0) {
        $sql = "
            UPDATE data_barcode_showa
            SET
                part_code   = ?,
                part_no     = ?,
                part_name   = ?,
                qty_polibag = ?,
                qty_box     = ?
            WHERE id = ?
        ";

        $params = array(
            $part_code,
            $part_no,
            $part_name,
            $qty_polibag,
            $qty_box,
            $id
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            send_json(array(
                "status" => "error",
                "message" => "Gagal update data.",
                "detail" => sqlsrv_errors()
            ));
        }

        send_json(array(
            "status" => "success",
            "message" => "Data berhasil diupdate.",
            "id" => $id
        ));
    } else {
        $sql = "
            INSERT INTO data_barcode_showa
                (part_code, part_no, part_name, qty_polibag, qty_box)
            VALUES
                (?, ?, ?, ?, ?)
        ";

        $params = array(
            $part_code,
            $part_no,
            $part_name,
            $qty_polibag,
            $qty_box
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            send_json(array(
                "status" => "error",
                "message" => "Gagal insert data baru.",
                "detail" => sqlsrv_errors()
            ));
        }

        send_json(array(
            "status" => "success",
            "message" => "Data baru berhasil disimpan."
        ));
    }
}

send_json(array(
    "status" => "error",
    "message" => "Invalid action."
));
?>