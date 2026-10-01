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

$di_id = intval(post_value("DI_ID"));

if ($di_id <= 0) {
    json_error("DI_ID kosong. Cari DI atau Save Header dulu.");
}


// ==========================================================
// AMBIL SEMUA LINE DI_PART
// ==========================================================
$sqlLines = "
    SELECT
        DI_ID,
        DIPA_LINO,
        PRICE_ID,
        ISNULL(PART_CODE, '') AS PART_CODE
    FROM DI_PART
    WHERE DI_ID = ?
    ORDER BY DIPA_LINO
";

$stmtLines = sqlsrv_query($conn, $sqlLines, array($di_id));

if ($stmtLines === false) {
    json_error("Gagal ambil data DI_PART: " . print_r(sqlsrv_errors(), true));
}

$lines = array();

while ($row = sqlsrv_fetch_array($stmtLines, SQLSRV_FETCH_ASSOC)) {
    $lines[] = array(
        "DI_ID"      => intval($row["DI_ID"]),
        "DIPA_LINO"  => intval($row["DIPA_LINO"]),
        "PRICE_ID"   => intval($row["PRICE_ID"]),
        "PART_CODE"  => trim($row["PART_CODE"])
    );
}

if (count($lines) == 0) {
    echo json_encode(array(
        "success" => true,
        "message" => "Tidak ada detail DI_PART yang perlu dibatalkan.",
        "deleted" => 0
    ));
    exit();
}


// ==========================================================
// ROLLBACK STOCK / FIFO PER LINE, LALU DELETE DI_PART
// ==========================================================
sqlsrv_begin_transaction($conn);

try {

    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];

        $sqlRollback = "
            SET NOCOUNT ON;
            EXEC dbo.sp_DI_PART_Rollback_Delete ?, ?
        ";

        $paramsRollback = array(
            $line["DI_ID"],
            $line["DIPA_LINO"]
        );

        $stmtRollback = sqlsrv_query($conn, $sqlRollback, $paramsRollback);

        if ($stmtRollback === false) {
            throw new Exception(
                "Rollback gagal pada line " .
                $line["DIPA_LINO"] .
                " / " .
                $line["PART_CODE"] .
                "\n\n" .
                print_r(sqlsrv_errors(), true)
            );
        }

        while (sqlsrv_next_result($stmtRollback)) {
            // habiskan semua resultset dari stored procedure
        }
    }

    $sqlDelete = "
        DELETE FROM DI_PART
        WHERE DI_ID = ?
    ";

    $stmtDelete = sqlsrv_query($conn, $sqlDelete, array($di_id));

    if ($stmtDelete === false) {
        throw new Exception(
            "Gagal delete DI_PART: " .
            print_r(sqlsrv_errors(), true)
        );
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success" => true,
        "message" => "Rollback stok sukses dan semua DI_PART telah dihapus.",
        "deleted" => count($lines)
    ));
    exit();

} catch (Exception $e) {

    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => "BATAL SEMUA DETAIL gagal: " . $e->getMessage()
    ));
    exit();
}
?>