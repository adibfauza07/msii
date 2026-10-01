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

function column_exists($conn, $tableName, $columnName) {
    $sql = "
        SELECT COUNT(*) AS CNT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($tableName, $columnName));

    if ($stmt === false) {
        return false;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$row) {
        return false;
    }

    return intval($row["CNT"]) > 0;
}

function safe_column($columnName) {
    return "[" . str_replace("]", "]]", $columnName) . "]";
}

function get_part_price_from_ordr_par($conn, $ordr_id, $ordp_lino, $price_id) {
    $candidateColumns = array(
        "PART_PRICE",
        "ORDP_PRICE",
        "PRICE",
        "UNIT_PRICE"
    );

    $priceColumn = "";

    for ($i = 0; $i < count($candidateColumns); $i++) {
        if (column_exists($conn, "ORDR_PAR", $candidateColumns[$i])) {
            $priceColumn = $candidateColumns[$i];
            break;
        }
    }

    if ($priceColumn == "") {
        return 0;
    }

    $sql = "
        SELECT TOP 1
            ISNULL(" . safe_column($priceColumn) . ", 0) AS PART_PRICE
        FROM ORDR_PAR
        WHERE CONVERT(VARCHAR(30), ORDR_ID) = ?
          AND CONVERT(VARCHAR(30), ORDP_LINO) = ?
          AND PRICE_ID = ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array(
        $ordr_id,
        $ordp_lino,
        $price_id
    ));

    if ($stmt === false) {
        return 0;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$row) {
        return 0;
    }

    return floatval($row["PART_PRICE"]);
}

function get_part_price_from_price($conn, $price_id) {
    $candidateColumns = array(
        "PART_PRICE",
        "PRICE",
        "PRICE_VALUE",
        "UNIT_PRICE",
        "PRICE_AMT"
    );

    $priceColumn = "";

    for ($i = 0; $i < count($candidateColumns); $i++) {
        if (column_exists($conn, "PRICE", $candidateColumns[$i])) {
            $priceColumn = $candidateColumns[$i];
            break;
        }
    }

    if ($priceColumn == "") {
        return 0;
    }

    $sql = "
        SELECT TOP 1
            ISNULL(" . safe_column($priceColumn) . ", 0) AS PART_PRICE
        FROM PRICE
        WHERE PRICE_ID = ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($price_id));

    if ($stmt === false) {
        return 0;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$row) {
        return 0;
    }

    return floatval($row["PART_PRICE"]);
}

$di_id      = intval(post_value("DI_ID"));
$dipa_lino  = intval(post_value("DIPA_LINO"));
$price_id   = intval(post_value("PRICE_ID"));

/*
    ORDR_ID jangan intval().
    Nilainya bisa minus / besar.
*/
$ordr_id    = post_value("ORDR_ID");
$ordp_lino  = post_value("ORDP_LINO");

if ($di_id <= 0) {
    json_error("DI_ID kosong.");
}

if ($dipa_lino <= 0) {
    json_error("DIPA_LINO kosong.");
}

if ($price_id <= 0) {
    json_error("PRICE_ID kosong.");
}

if ($ordr_id == "") {
    json_error("ORDR_ID kosong.");
}

if ($ordp_lino == "") {
    json_error("ORDP_LINO kosong.");
}

/*
    Ambil QTY schedule dari DI_PART.
*/
$sqlDI = "
    SELECT
        ISNULL(DIPA_QTY, 0) AS DIPA_QTY
    FROM DI_PART
    WHERE DI_ID = ?
      AND DIPA_LINO = ?
      AND PRICE_ID = ?
";

$stmtDI = sqlsrv_query($conn, $sqlDI, array(
    $di_id,
    $dipa_lino,
    $price_id
));

if ($stmtDI === false) {
    json_error("Gagal cek DI_PART: " . print_r(sqlsrv_errors(), true));
}

$rowDI = sqlsrv_fetch_array($stmtDI, SQLSRV_FETCH_ASSOC);

if (!$rowDI) {
    json_error("Data DI_PART tidak ditemukan.");
}

$schedule_qty = intval($rowDI["DIPA_QTY"]);

if ($schedule_qty <= 0) {
    json_error("QTY schedule kosong.");
}

/*
    Hitung total allocated line ini.
*/
$sqlAllocated = "
    SELECT ISNULL(SUM(QTY), 0) AS ALLOCATED_QTY
    FROM DIPA_PAR
    WHERE DI_ID = ?
      AND DIPA_LINO = ?
      AND PRICE_ID = ?
";

$stmtAllocated = sqlsrv_query($conn, $sqlAllocated, array(
    $di_id,
    $dipa_lino,
    $price_id
));

if ($stmtAllocated === false) {
    json_error("Gagal cek DIPA_PAR: " . print_r(sqlsrv_errors(), true));
}

$rowAllocated = sqlsrv_fetch_array($stmtAllocated, SQLSRV_FETCH_ASSOC);
$allocated_qty = intval($rowAllocated["ALLOCATED_QTY"]);

$need_qty = $schedule_qty - $allocated_qty;

if ($need_qty <= 0) {
    json_error(
        "QTY schedule sudah terpenuhi.\n\n" .
        "Schedule Qty: " . $schedule_qty . "\n" .
        "Allocated Qty: " . $allocated_qty
    );
}

/*
    1. Coba cari ORDR_PAR dengan ORDR_ID + ORDP_LINO + PRICE_ID exact.
*/
$sqlPOExact = "
    SELECT TOP 1
        CONVERT(VARCHAR(30), ORDR_ID) AS ORDR_ID,
        CONVERT(VARCHAR(30), ORDP_LINO) AS ORDP_LINO,
        ISNULL(ORDP_QTY, 0) AS ORDP_QTY,
        ISNULL(ORDP_DQTY, 0) AS ORDP_DQTY,
        ISNULL(ORDP_BQTY, 0) AS ORDP_BQTY
    FROM ORDR_PAR
    WHERE CONVERT(VARCHAR(30), ORDR_ID) = ?
      AND CONVERT(VARCHAR(30), ORDP_LINO) = ?
      AND PRICE_ID = ?
";

$stmtPO = sqlsrv_query($conn, $sqlPOExact, array(
    $ordr_id,
    $ordp_lino,
    $price_id
));

if ($stmtPO === false) {
    json_error("Gagal cek ORDR_PAR exact: " . print_r(sqlsrv_errors(), true));
}

$rowPO = sqlsrv_fetch_array($stmtPO, SQLSRV_FETCH_ASSOC);

$po_found_mode = "exact";

/*
    2. Kalau exact tidak ketemu, fallback:
       cari dari ORDR_ID + PRICE_ID + balance > 0.
       Ini untuk kasus ORDP_LINO dari grid tidak cocok.
*/
if (!$rowPO) {
    $sqlPOFallback = "
        SELECT TOP 1
            CONVERT(VARCHAR(30), ORDR_ID) AS ORDR_ID,
            CONVERT(VARCHAR(30), ORDP_LINO) AS ORDP_LINO,
            ISNULL(ORDP_QTY, 0) AS ORDP_QTY,
            ISNULL(ORDP_DQTY, 0) AS ORDP_DQTY,
            ISNULL(ORDP_BQTY, 0) AS ORDP_BQTY
        FROM ORDR_PAR
        WHERE CONVERT(VARCHAR(30), ORDR_ID) = ?
          AND PRICE_ID = ?
          AND ISNULL(ORDP_BQTY, 0) > 0
        ORDER BY ORDP_LINO
    ";

    $stmtPOFallback = sqlsrv_query($conn, $sqlPOFallback, array(
        $ordr_id,
        $price_id
    ));

    if ($stmtPOFallback === false) {
        json_error("Gagal cek ORDR_PAR fallback: " . print_r(sqlsrv_errors(), true));
    }

    $rowPO = sqlsrv_fetch_array($stmtPOFallback, SQLSRV_FETCH_ASSOC);
    $po_found_mode = "fallback";
}

if (!$rowPO) {
    json_error(
        "Data PO tidak ditemukan di ORDR_PAR.\n\n" .
        "ORDR_ID: " . $ordr_id . "\n" .
        "ORDP_LINO kiriman: " . $ordp_lino . "\n" .
        "PRICE_ID: " . $price_id . "\n\n" .
        "Cek ORDR_PAR berdasarkan ORDR_ID dan PRICE_ID."
    );
}

/*
    Pakai ORDP_LINO aktual dari ORDR_PAR.
*/
$actual_ordr_id   = trim($rowPO["ORDR_ID"]);
$actual_ordp_lino = trim($rowPO["ORDP_LINO"]);
$po_balance       = intval($rowPO["ORDP_BQTY"]);

if ($po_balance <= 0) {
    json_error(
        "Balance PO sudah 0.\n\n" .
        "ORDR_ID: " . $actual_ordr_id . "\n" .
        "ORDP_LINO: " . $actual_ordp_lino . "\n" .
        "PRICE_ID: " . $price_id
    );
}

$allocate_qty = $need_qty;

if ($po_balance < $allocate_qty) {
    $allocate_qty = $po_balance;
}

if ($allocate_qty <= 0) {
    json_error("Qty allocate kosong.");
}

/*
    PART_PRICE wajib di DIPA_PAR.
*/
$part_price = get_part_price_from_ordr_par(
    $conn,
    $actual_ordr_id,
    $actual_ordp_lino,
    $price_id
);

if ($part_price == 0) {
    $part_price = get_part_price_from_price($conn, $price_id);
}

if ($part_price === null) {
    $part_price = 0;
}

sqlsrv_begin_transaction($conn);

try {

    /*
        Cek apakah alokasi PO ini sudah ada.
    */
    $sqlCheck = "
        SELECT COUNT(*) AS CNT
        FROM DIPA_PAR
        WHERE DI_ID = ?
          AND DIPA_LINO = ?
          AND PRICE_ID = ?
          AND CONVERT(VARCHAR(30), ORDR_ID) = ?
          AND CONVERT(VARCHAR(30), ORDP_LINO) = ?
    ";

    $stmtCheck = sqlsrv_query($conn, $sqlCheck, array(
        $di_id,
        $dipa_lino,
        $price_id,
        $actual_ordr_id,
        $actual_ordp_lino
    ));

    if ($stmtCheck === false) {
        throw new Exception("Gagal cek existing DIPA_PAR: " . print_r(sqlsrv_errors(), true));
    }

    $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    $exists = intval($rowCheck["CNT"]) > 0;

    if ($exists) {

        $sqlUpdateDipa = "
            UPDATE DIPA_PAR
            SET
                QTY = ISNULL(QTY, 0) + ?,
                PART_PRICE = ?
            WHERE DI_ID = ?
              AND DIPA_LINO = ?
              AND PRICE_ID = ?
              AND CONVERT(VARCHAR(30), ORDR_ID) = ?
              AND CONVERT(VARCHAR(30), ORDP_LINO) = ?
        ";

        $stmtUpdateDipa = sqlsrv_query($conn, $sqlUpdateDipa, array(
            $allocate_qty,
            $part_price,
            $di_id,
            $dipa_lino,
            $price_id,
            $actual_ordr_id,
            $actual_ordp_lino
        ));

        if ($stmtUpdateDipa === false) {
            throw new Exception("Gagal update DIPA_PAR: " . print_r(sqlsrv_errors(), true));
        }

    } else {

        $sqlInsertDipa = "
            INSERT INTO DIPA_PAR
            (
                DI_ID,
                DIPA_LINO,
                PRICE_ID,
                ORDR_ID,
                ORDP_LINO,
                QTY,
                PART_PRICE
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?
            )
        ";

        $stmtInsertDipa = sqlsrv_query($conn, $sqlInsertDipa, array(
            $di_id,
            $dipa_lino,
            $price_id,
            $actual_ordr_id,
            $actual_ordp_lino,
            $allocate_qty,
            $part_price
        ));

        if ($stmtInsertDipa === false) {
            throw new Exception("Gagal insert DIPA_PAR: " . print_r(sqlsrv_errors(), true));
        }
    }

    /*
        Update ORDR_PAR berdasarkan ORDR_ID dan ORDP_LINO aktual.
    */
    $sqlUpdatePO = "
        UPDATE ORDR_PAR
        SET
            ORDP_DQTY = ISNULL(ORDP_DQTY, 0) + ?,
            ORDP_BQTY =
                CASE
                    WHEN ISNULL(ORDP_BQTY, 0) - ? < 0 THEN 0
                    ELSE ISNULL(ORDP_BQTY, 0) - ?
                END
        WHERE CONVERT(VARCHAR(30), ORDR_ID) = ?
          AND CONVERT(VARCHAR(30), ORDP_LINO) = ?
          AND PRICE_ID = ?
    ";

    $stmtUpdatePO = sqlsrv_query($conn, $sqlUpdatePO, array(
        $allocate_qty,
        $allocate_qty,
        $allocate_qty,
        $actual_ordr_id,
        $actual_ordp_lino,
        $price_id
    ));

    if ($stmtUpdatePO === false) {
        throw new Exception("Gagal update ORDR_PAR: " . print_r(sqlsrv_errors(), true));
    }

    $affected = sqlsrv_rows_affected($stmtUpdatePO);

    if ($affected === 0) {
        throw new Exception(
            "Update ORDR_PAR tidak mengubah data.\n" .
            "ORDR_ID: " . $actual_ordr_id . "\n" .
            "ORDP_LINO: " . $actual_ordp_lino . "\n" .
            "PRICE_ID: " . $price_id
        );
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success" => true,
        "message" =>
            "ALLOCATE PO berhasil.\n\n" .
            "Mode PO Find: " . $po_found_mode . "\n" .
            "ORDR_ID: " . $actual_ordr_id . "\n" .
            "ORDP_LINO aktual: " . $actual_ordp_lino . "\n" .
            "Qty Allocate: " . $allocate_qty . "\n" .
            "Part Price: " . $part_price,
        "qty" => $allocate_qty,
        "part_price" => $part_price,
        "ORDR_ID" => $actual_ordr_id,
        "ORDP_LINO" => $actual_ordp_lino
    ));
    exit();

} catch (Exception $e) {

    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => "ALLOCATE PO gagal: " . $e->getMessage()
    ));
    exit();
}
?>