<?php
require_once __DIR__ . '/config/database.php';

header("Content-Type: application/json");

if ($conn === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Koneksi database gagal."
    ));
    exit();
}

function json_error($msg) {
    echo json_encode(array(
        "success" => false,
        "message" => $msg
    ));
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

$di_id      = intval(post_value("DI_ID"));
$dipa_lino  = intval(post_value("DIPA_LINO"));
$price_id   = intval(post_value("PRICE_ID"));

if ($di_id <= 0) {
    json_error("DI_ID kosong.");
}

if ($dipa_lino <= 0) {
    json_error("DIPA_LINO kosong.");
}

/*
    Cek line DI_PART.
*/
$sqlCheck = "
    SELECT TOP 1
        DI_ID,
        DIPA_LINO,
        PRICE_ID,
        ISNULL(DIPA_QTY, 0) AS DIPA_QTY
    FROM DI_PART
    WHERE DI_ID = ?
      AND DIPA_LINO = ?
";

$stmtCheck = sqlsrv_query($conn, $sqlCheck, array(
    $di_id,
    $dipa_lino
));

if ($stmtCheck === false) {
    json_error("Gagal cek DI_PART: " . print_r(sqlsrv_errors(), true));
}

$rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

if (!$rowCheck) {
    echo json_encode(array(
        "success" => true,
        "message" => "Line belum tersimpan di database. Baris boleh dihapus dari tampilan.",
        "deleted" => 0
    ));
    exit();
}

if ($price_id <= 0) {
    $price_id = intval($rowCheck["PRICE_ID"]);
}

sqlsrv_begin_transaction($conn);

try {

    /*
        Ambil semua alokasi PO dari DIPA_PAR untuk line ini.
        Sebelum delete DIPA_PAR, kembalikan QTY ke ORDR_PAR.
    */
    $sqlAllocated = "
        SELECT
            CONVERT(VARCHAR(30), ORDR_ID) AS ORDR_ID,
            CONVERT(VARCHAR(30), ORDP_LINO) AS ORDP_LINO,
            PRICE_ID,
            ISNULL(QTY, 0) AS QTY
        FROM DIPA_PAR
        WHERE DI_ID = ?
          AND DIPA_LINO = ?
    ";

    $stmtAllocated = sqlsrv_query($conn, $sqlAllocated, array(
        $di_id,
        $dipa_lino
    ));

    if ($stmtAllocated === false) {
        throw new Exception("Gagal ambil DIPA_PAR: " . print_r(sqlsrv_errors(), true));
    }

    while ($rowAlloc = sqlsrv_fetch_array($stmtAllocated, SQLSRV_FETCH_ASSOC)) {
        $ordr_id    = trim($rowAlloc["ORDR_ID"]);
        $ordp_lino  = trim($rowAlloc["ORDP_LINO"]);
        $alloc_price = intval($rowAlloc["PRICE_ID"]);
        $qty         = intval($rowAlloc["QTY"]);

        if ($qty > 0 && $ordr_id != "" && $ordp_lino != "") {
            $sqlBackPO = "
                UPDATE ORDR_PAR
                SET
                    ORDP_DQTY =
                        CASE
                            WHEN ISNULL(ORDP_DQTY, 0) - ? < 0 THEN 0
                            ELSE ISNULL(ORDP_DQTY, 0) - ?
                        END,
                    ORDP_BQTY = ISNULL(ORDP_BQTY, 0) + ?
                WHERE CONVERT(VARCHAR(30), ORDR_ID) = ?
                  AND CONVERT(VARCHAR(30), ORDP_LINO) = ?
                  AND PRICE_ID = ?
            ";

            $stmtBackPO = sqlsrv_query($conn, $sqlBackPO, array(
                $qty,
                $qty,
                $qty,
                $ordr_id,
                $ordp_lino,
                $alloc_price
            ));

            if ($stmtBackPO === false) {
                throw new Exception("Gagal rollback ORDR_PAR: " . print_r(sqlsrv_errors(), true));
            }
        }
    }

    /*
        Hapus alokasi PO line ini.
    */
    $sqlDeleteDipa = "
        DELETE FROM DIPA_PAR
        WHERE DI_ID = ?
          AND DIPA_LINO = ?
    ";

    $stmtDeleteDipa = sqlsrv_query($conn, $sqlDeleteDipa, array(
        $di_id,
        $dipa_lino
    ));

    if ($stmtDeleteDipa === false) {
        throw new Exception("Gagal delete DIPA_PAR: " . print_r(sqlsrv_errors(), true));
    }

    /*
        Rollback FIFO / stock jika stored procedure ada.
        Kalau SP gagal karena belum pernah post FIFO, proses tetap lanjut.
    */
    $sqlRollbackFifo = "
        SET NOCOUNT ON;
        EXEC dbo.sp_DI_PART_Rollback_Delete ?, ?
    ";

    $stmtRollbackFifo = sqlsrv_query($conn, $sqlRollbackFifo, array(
        $di_id,
        $dipa_lino
    ));

    if ($stmtRollbackFifo !== false) {
        while (sqlsrv_next_result($stmtRollbackFifo)) {
            // habiskan resultset
        }
    }

    /*
        Hapus line DI_PART.
    */
    $sqlDeleteLine = "
        DELETE FROM DI_PART
        WHERE DI_ID = ?
          AND DIPA_LINO = ?
    ";

    $stmtDeleteLine = sqlsrv_query($conn, $sqlDeleteLine, array(
        $di_id,
        $dipa_lino
    ));

    if ($stmtDeleteLine === false) {
        throw new Exception("Gagal delete DI_PART: " . print_r(sqlsrv_errors(), true));
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success" => true,
        "message" => "Line " . $dipa_lino . " berhasil dihapus.",
        "deleted" => 1
    ));
    exit();

} catch (Exception $e) {

    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => "Hapus per baris gagal: " . $e->getMessage()
    ));
    exit();
}
?>