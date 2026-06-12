<?php
require_once __DIR__ . "/../config/database_ordering.php";

header("Content-Type: application/json; charset=utf-8");

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

$ordr_id_raw      = post_value("ORDR_ID");
$cust_id          = intval(post_value("CUST_ID"));
$ordr_po          = post_value("ORDR_PO");
$ordr_date        = post_value("ORDR_DATE");
$ordr_curr        = post_value("ORDR_CURR");
$ordr_rem         = post_value("ORDR_REM");
$ordr_replacement = intval(post_value("ORDR_REPLACEMENT"));
$ordr_close       = intval(post_value("ORDR_CLOSE"));

$ordp_lino  = intval(post_value("ORDP_LINO"));
$price_id   = intval(post_value("PRICE_ID"));
$ordp_price = floatval(post_value("ORDP_PRICE"));
$ordp_qty   = intval(post_value("ORDP_QTY"));
$ordp_dqty  = intval(post_value("ORDP_DQTY"));
$ordp_rem   = post_value("ORDP_REM");
$ordp_close = intval(post_value("ORDP_CLOSE"));

if ($cust_id <= 0) {
    json_error("Customer belum dipilih.");
}

if ($ordr_po == "") {
    json_error("ORDER PO belum diisi.");
}

if ($ordr_date == "") {
    json_error("ORDER DATE belum diisi.");
}

if ($ordr_curr == "") {
    $ordr_curr = "IDR";
}

if ($ordp_lino <= 0) {
    json_error("Line number tidak valid.");
}

if ($price_id <= 0) {
    json_error("PRICE_ID kosong pada line " . $ordp_lino);
}

if ($ordp_qty <= 0) {
    json_error("QTY harus lebih dari 0 pada line " . $ordp_lino);
}

if ($ordp_dqty < 0) {
    json_error("DELIVERY tidak boleh minus pada line " . $ordp_lino);
}

if ($ordp_qty < $ordp_dqty) {
    json_error("QTY tidak boleh lebih kecil dari DELIVERY pada line " . $ordp_lino);
}

$ordp_bqty = $ordp_qty - $ordp_dqty;

if (!sqlsrv_begin_transaction($conn)) {
    json_error("Gagal mulai transaksi: " . print_r(sqlsrv_errors(), true));
}

try {
    $ordr_id = 0;

    if ($ordr_id_raw !== "") {
        if (!is_numeric($ordr_id_raw)) {
            throw new Exception("ORDR_ID tidak valid: " . $ordr_id_raw);
        }

        $ordr_id = intval($ordr_id_raw);
    }

    /*
        Kalau ORDR_ID kosong, cek PO dulu.
        Jika PO sudah ada, pakai ORDR_ID dari PO itu.
        Jika PO belum ada, insert header baru.
    */
    if ($ordr_id_raw === "") {
        $sqlFindPo = "
            SET NOCOUNT ON;

            SELECT TOP 1 ORDR_ID
            FROM dbo.ORDERS
            WHERE ORDR_PO = ?
            ORDER BY ORDR_ID DESC
        ";

        $stmtFindPo = sqlsrv_query($conn, $sqlFindPo, array($ordr_po));

        if ($stmtFindPo === false) {
            throw new Exception("Gagal cek PO: " . print_r(sqlsrv_errors(), true));
        }

        $foundPo = sqlsrv_fetch_array($stmtFindPo, SQLSRV_FETCH_ASSOC);

        if ($foundPo && isset($foundPo["ORDR_ID"])) {
            $ordr_id = intval($foundPo["ORDR_ID"]);
        } else {
            $sqlInsertHeader = "
                SET NOCOUNT ON;

                INSERT INTO dbo.ORDERS
                (
                    CUST_ID,
                    ORDR_PO,
                    ORDR_DATE,
                    ORDR_REM,
                    ORDR_CURR,
                    ORDR_CLOSE,
                    ORDR_REPLACEMENT,
                    ORDR_PENDING
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, 0
                );

                SELECT TOP 1 ORDR_ID
                FROM dbo.ORDERS
                WHERE ORDR_PO = ?
                ORDER BY ORDR_ID DESC;
            ";

            $paramsHeader = array(
                $cust_id,
                $ordr_po,
                $ordr_date,
                $ordr_rem,
                $ordr_curr,
                $ordr_close,
                $ordr_replacement,
                $ordr_po
            );

            $stmtHeader = sqlsrv_query($conn, $sqlInsertHeader, $paramsHeader);

            if ($stmtHeader === false) {
                throw new Exception("Insert header gagal: " . print_r(sqlsrv_errors(), true));
            }

            $h = sqlsrv_fetch_array($stmtHeader, SQLSRV_FETCH_ASSOC);

            if (!$h || !isset($h["ORDR_ID"])) {
                throw new Exception("Gagal mengambil ORDR_ID setelah insert header.");
            }

            $ordr_id = intval($h["ORDR_ID"]);
        }
    } else {
        $sqlUpdateHeader = "
            SET NOCOUNT ON;

            UPDATE dbo.ORDERS
            SET
                CUST_ID = ?,
                ORDR_PO = ?,
                ORDR_DATE = ?,
                ORDR_REM = ?,
                ORDR_CURR = ?,
                ORDR_CLOSE = ?,
                ORDR_REPLACEMENT = ?
            WHERE ORDR_ID = ?
        ";

        $stmtUpdateHeader = sqlsrv_query($conn, $sqlUpdateHeader, array(
            $cust_id,
            $ordr_po,
            $ordr_date,
            $ordr_rem,
            $ordr_curr,
            $ordr_close,
            $ordr_replacement,
            $ordr_id
        ));

        if ($stmtUpdateHeader === false) {
            throw new Exception("Update header gagal: " . print_r(sqlsrv_errors(), true));
        }
    }

    /*
        Kalau line sama sudah ada, update.
        Kalau belum ada, insert detail baru.
    */
   $sqlCheckDuplicateItem = "
    SET NOCOUNT ON;

    SELECT TOP 1
        ORDP_LINO
    FROM dbo.ORDR_PAR
    WHERE ORDR_ID = ?
      AND PRICE_ID = ?
      AND ORDP_LINO <> ?
";

$stmtCheckDuplicateItem = sqlsrv_query($conn, $sqlCheckDuplicateItem, array(
    $ordr_id,
    $price_id,
    $ordp_lino
));

if ($stmtCheckDuplicateItem === false) {
    throw new Exception("Cek duplicate item gagal: " . print_r(sqlsrv_errors(), true));
}

$dup = sqlsrv_fetch_array($stmtCheckDuplicateItem, SQLSRV_FETCH_ASSOC);

if ($dup) {
    throw new Exception(
        "Item sudah ada di line " . intval($dup["ORDP_LINO"]) .
        ". Tidak boleh input item yang sama dua kali."
    );
}   


   $sqlCheckLine = "
        SET NOCOUNT ON;

        SELECT TOP 1
            ORDR_ID,
            ORDP_LINO
        FROM dbo.ORDR_PAR
        WHERE ORDR_ID = ?
          AND ORDP_LINO = ?
    ";

    $stmtCheckLine = sqlsrv_query($conn, $sqlCheckLine, array($ordr_id, $ordp_lino));

    if ($stmtCheckLine === false) {
        throw new Exception("Cek detail gagal: " . print_r(sqlsrv_errors(), true));
    }

    $lineExist = sqlsrv_fetch_array($stmtCheckLine, SQLSRV_FETCH_ASSOC) ? true : false;

    if ($lineExist) {
        $sqlSaveLine = "
            SET NOCOUNT ON;

            UPDATE dbo.ORDR_PAR
            SET
                PRICE_ID = ?,
                ORDP_PRICE = ?,
                ORDP_QTY = ?,
                ORDP_DQTY = ?,
                ORDP_BQTY = ?,
                ORDP_CLOSE = ?,
                ORDP_REM = ?
            WHERE ORDR_ID = ?
              AND ORDP_LINO = ?
        ";

        $paramsLine = array(
            $price_id,
            $ordp_price,
            $ordp_qty,
            $ordp_dqty,
            $ordp_bqty,
            $ordp_close,
            $ordp_rem,
            $ordr_id,
            $ordp_lino
        );

        $action = "update";
    } else {
        $sqlSaveLine = "
            SET NOCOUNT ON;

            INSERT INTO dbo.ORDR_PAR
            (
                ORDR_ID,
                ORDP_LINO,
                PRICE_ID,
                ORDP_PRICE,
                ORDP_QTY,
                ORDP_DQTY,
                ORDP_BQTY,
                ORDP_CLOSE,
                ORDP_REM
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ";

        $paramsLine = array(
            $ordr_id,
            $ordp_lino,
            $price_id,
            $ordp_price,
            $ordp_qty,
            $ordp_dqty,
            $ordp_bqty,
            $ordp_close,
            $ordp_rem
        );

        $action = "insert";
    }

    $stmtSaveLine = sqlsrv_query($conn, $sqlSaveLine, $paramsLine);

    if ($stmtSaveLine === false) {
        throw new Exception("Simpan detail gagal: " . print_r(sqlsrv_errors(), true));
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success" => true,
        "message" => "Detail line " . $ordp_lino . " berhasil disimpan.",
        "ORDR_ID" => $ordr_id,
        "ORDP_LINO" => $ordp_lino,
        "ORDP_BQTY" => $ordp_bqty,
        "action" => $action
    ));
    exit();

} catch (Exception $e) {
    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => "Simpan detail gagal: " . $e->getMessage()
    ));
    exit();
}
?>