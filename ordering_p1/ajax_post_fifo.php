<?php
require_once __DIR__ . "/../config/database_ordering.php";

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

function get_po_qty_optional($conn, $di_id, $dipa_lino) {
    /*
        Validasi optional.
        Umumnya alokasi PO tersimpan di DIPA_PAR.
        Kalau struktur tabel berbeda, function ini akan return null dan POST FIFO tetap lanjut.
    */

    $sql = "
        SELECT ISNULL(SUM(QTY), 0) AS PO_QTY
        FROM DIPA_PAR
        WHERE DI_ID = ?
          AND DIPA_LINO = ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($di_id, $dipa_lino));

    if ($stmt === false) {
        return null;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return intval($row["PO_QTY"]);
}

$di_id = intval(post_value("DI_ID"));

if ($di_id <= 0) {
    json_error("DI_ID kosong. Cari DI atau Save Header dulu.");
}


// ==========================================================
// AMBIL SEMUA DETAIL DI_PART UNTUK DI_ID
// ==========================================================
$sqlDetail = "
    SELECT
        DP.DI_ID,
        DP.DIPA_LINO,
        DP.PRICE_ID,
        ISNULL(DP.DIPA_QTY, 0) AS DIPA_QTY,
        ISNULL(I.ITEM_CODE, ISNULL(DP.PART_CODE, '')) AS CODE,
        ISNULL(I.ITEM_NAME, '') AS NAME
    FROM DI_PART DP
    LEFT JOIN PRICE P ON P.PRICE_ID = DP.PRICE_ID
    LEFT JOIN ITEMS I ON I.ITEM_ID = P.PART_ID
    WHERE DP.DI_ID = ?
    ORDER BY DP.DIPA_LINO
";

$stmtDetail = sqlsrv_query($conn, $sqlDetail, array($di_id));

if ($stmtDetail === false) {
    json_error("Query DI_PART gagal: " . print_r(sqlsrv_errors(), true));
}

$rows = array();

while ($row = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}

if (count($rows) == 0) {
    json_error("Tidak ada detail DI_PART untuk DI_ID: " . $di_id);
}


// ==========================================================
// POST FIFO PER LINE
// Sama seperti Delphi:
// sp_DI_PART_FIFO_PostProcess @DI_ID, @PRICE_ID, @DIPA_LINO
// ==========================================================
$processed = 0;
$warnings = array();
$validationSkipped = false;

for ($i = 0; $i < count($rows); $i++) {

    $r = $rows[$i];

    $line_no  = intval($r["DIPA_LINO"]);
    $price_id = intval($r["PRICE_ID"]);
    $sch_qty  = intval($r["DIPA_QTY"]);
    $code     = trim($r["CODE"]);
    $name     = trim($r["NAME"]);

    if ($line_no <= 0) {
        json_error("DIPA_LINO kosong pada baris ke-" . ($i + 1));
    }

    if ($price_id <= 0) {
        json_error("PRICE_ID kosong pada line " . $line_no . " / " . $code);
    }

    sqlsrv_begin_transaction($conn);

    try {

        $sqlSP = "
            SET NOCOUNT ON;
            EXEC sp_DI_PART_FIFO_PostProcess ?, ?, ?
        ";

        $paramsSP = array(
            $di_id,
            $price_id,
            $line_no
        );

        $stmtSP = sqlsrv_query($conn, $sqlSP, $paramsSP);

        if ($stmtSP === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        // habiskan resultset kalau SP mengeluarkan beberapa result
        while (sqlsrv_next_result($stmtSP)) {
        }

        sqlsrv_commit($conn);

        $processed++;

    } catch (Exception $e) {

        sqlsrv_rollback($conn);

        echo json_encode(array(
            "success" => false,
            "message" =>
                "POST FIFO gagal pada line " . $line_no .
                " / " . $code . "\n\n" .
                $e->getMessage(),
            "processed" => $processed
        ));
        exit();
    }


    // ======================================================
    // Validasi optional: Schedule Qty vs PO Qty
    // Kalau DIPA_PAR tidak cocok, validasi dilewati.
    // ======================================================
    $po_qty = get_po_qty_optional($conn, $di_id, $line_no);

    if ($po_qty === null) {
        $validationSkipped = true;
    } else {
        if ($sch_qty != $po_qty) {
            $warnings[] = array(
                "DIPA_LINO" => $line_no,
                "CODE"      => $code,
                "NAME"      => $name,
                "SCH_QTY"   => $sch_qty,
                "PO_QTY"    => $po_qty
            );
        }
    }
}


// ==========================================================
// RESPONSE
// ==========================================================
$message = "POST FIFO selesai. Total line diproses: " . $processed . ".";

if (count($warnings) > 0) {
    $message .= "\n\nQTY SCH tidak sama dengan QTY PO pada beberapa line.";
}

if ($validationSkipped) {
    $message .= "\n\nCatatan: Validasi PO Qty dilewati karena query DIPA_PAR tidak cocok / belum tersedia.";
}

echo json_encode(array(
    "success" => true,
    "message" => $message,
    "processed" => $processed,
    "warnings" => $warnings,
    "validation_skipped" => $validationSkipped
));
exit();
?>