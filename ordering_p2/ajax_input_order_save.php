<?php
require_once __DIR__ . "/../config/db_plant2.php";

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
$rows_json        = post_value("ROWS_JSON");

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

if ($rows_json == "") {
    json_error("Detail order kosong.");
}

$rows = json_decode($rows_json, true);

if (!is_array($rows) || count($rows) == 0) {
    json_error("Format detail order tidak valid.");
}

/*
    ORDR_ID bisa minus.
    0 artinya header belum ada / order baru.
*/
$ordr_id = 0;

if ($ordr_id_raw !== "") {
    if (!is_numeric($ordr_id_raw)) {
        json_error("ORDR_ID tidak valid: " . $ordr_id_raw);
    }

    $ordr_id = intval($ordr_id_raw);
}

/*
    Validasi detail.
*/
$priceCheck = array();

for ($i = 0; $i < count($rows); $i++) {
    $line = $rows[$i];

    $lino    = isset($line["ORDP_LINO"]) ? intval($line["ORDP_LINO"]) : 0;
    $priceId = isset($line["PRICE_ID"]) ? intval($line["PRICE_ID"]) : 0;
    $qty     = isset($line["ORDP_QTY"]) ? intval($line["ORDP_QTY"]) : 0;
    $dqty    = isset($line["ORDP_DQTY"]) ? intval($line["ORDP_DQTY"]) : 0;
    $price   = isset($line["ORDP_PRICE"]) ? floatval($line["ORDP_PRICE"]) : 0;

    if ($lino <= 0) {
        json_error("Line number tidak valid.");
    }

    if ($priceId <= 0) {
        json_error("PRICE_ID kosong pada line " . $lino);
    }

    if ($qty <= 0) {
        json_error("QTY harus lebih dari 0 pada line " . $lino);
    }

    if ($dqty < 0) {
        json_error("DELIVERY tidak boleh minus pada line " . $lino);
    }

    if ($qty < $dqty) {
        json_error("QTY tidak boleh lebih kecil dari DELIVERY pada line " . $lino);
    }

    if ($price < 0) {
        json_error("PRICE tidak boleh minus pada line " . $lino);
    }

    /*
        Cegah item duplicate dalam grid yang sedang disimpan.
    */
    if (isset($priceCheck[$priceId])) {
        json_error(
            "Item duplicate di grid.\n\n" .
            "PRICE_ID : " . $priceId . "\n" .
            "Line lama : " . $priceCheck[$priceId] . "\n" .
            "Line baru : " . $lino
        );
    }

    $priceCheck[$priceId] = $lino;
}

if (!sqlsrv_begin_transaction($conn)) {
    json_error("Gagal mulai transaksi: " . print_r(sqlsrv_errors(), true));
}

try {
    /*
        Cek ORDER PO + ORDER DATE.
        PO boleh sama asal tanggal berbeda.
    */
    $sqlFindPo = "
        SET NOCOUNT ON;

        SELECT TOP 1
            ORDR_ID
        FROM dbo.ORDERS
        WHERE ORDR_PO = ?
          AND ORDR_DATE >= CONVERT(datetime, ?)
          AND ORDR_DATE < DATEADD(day, 1, CONVERT(datetime, ?))
        ORDER BY ORDR_ID DESC
    ";

    $stmtFindPo = sqlsrv_query($conn, $sqlFindPo, array(
        $ordr_po,
        $ordr_date,
        $ordr_date
    ));

    if ($stmtFindPo === false) {
        throw new Exception("Gagal cek PO duplicate: " . print_r(sqlsrv_errors(), true));
    }

    $foundPo = sqlsrv_fetch_array($stmtFindPo, SQLSRV_FETCH_ASSOC);

    if ($foundPo && isset($foundPo["ORDR_ID"])) {
        $existOrdrId = intval($foundPo["ORDR_ID"]);

        /*
            Kalau form belum punya ORDR_ID,
            berarti pakai order lama dengan PO + tanggal yang sama.
        */
        if ($ordr_id == 0) {
            $ordr_id = $existOrdrId;
        } else {
            /*
                Kalau form sudah punya ORDR_ID tapi PO + tanggal
                ternyata milik order lain, baru ditolak.
            */
            if ($existOrdrId != $ordr_id) {
                throw new Exception(
                    "ORDER PO dan tanggal sudah dipakai order lain.\n\n" .
                    "ORDER PO : " . $ordr_po . "\n" .
                    "Tanggal  : " . $ordr_date . "\n" .
                    "ORDR_ID lama : " . $existOrdrId . "\n" .
                    "ORDR_ID sekarang : " . $ordr_id
                );
            }
        }
    }

    /*
        Kalau ORDR_ID masih 0, insert header baru.
        Jangan pakai OUTPUT INSERTED karena tabel ORDERS bisa ada trigger.
    */
    if ($ordr_id == 0) {
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

            SELECT TOP 1
                ORDR_ID
            FROM dbo.ORDERS
            WHERE ORDR_PO = ?
              AND ORDR_DATE >= CONVERT(datetime, ?)
              AND ORDR_DATE < DATEADD(day, 1, CONVERT(datetime, ?))
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
            $ordr_po,
            $ordr_date,
            $ordr_date
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

        if ($ordr_id == 0) {
            throw new Exception("ORDR_ID baru kosong / 0.");
        }
    } else {
        /*
            Kalau order lama, update header.
        */
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

        $paramsUpdateHeader = array(
            $cust_id,
            $ordr_po,
            $ordr_date,
            $ordr_rem,
            $ordr_curr,
            $ordr_close,
            $ordr_replacement,
            $ordr_id
        );

        $stmtUpdateHeader = sqlsrv_query($conn, $sqlUpdateHeader, $paramsUpdateHeader);

        if ($stmtUpdateHeader === false) {
            throw new Exception("Update header gagal: " . print_r(sqlsrv_errors(), true));
        }
    }

    /*
        Upsert detail.
        Kalau line sudah ada -> UPDATE.
        Kalau line belum ada -> INSERT.
    */
    $saved    = 0;
    $inserted = 0;
    $updated  = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $line = $rows[$i];

        $lino    = intval($line["ORDP_LINO"]);
        $priceId = intval($line["PRICE_ID"]);
        $price   = floatval($line["ORDP_PRICE"]);
        $qty     = intval($line["ORDP_QTY"]);
        $dqty    = isset($line["ORDP_DQTY"]) ? intval($line["ORDP_DQTY"]) : 0;
        $bqty    = $qty - $dqty;
        $closed  = isset($line["ORDP_CLOSE"]) ? intval($line["ORDP_CLOSE"]) : 0;
        $remark  = isset($line["ORDP_REM"]) ? trim($line["ORDP_REM"]) : "";

        /*
            Cegah duplicate item di database untuk order yang sama.
            PRICE_ID tidak boleh ada di line lain.
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
            $priceId,
            $lino
        ));

        if ($stmtCheckDuplicateItem === false) {
            throw new Exception(
                "Cek duplicate item line " . $lino . " gagal: " .
                print_r(sqlsrv_errors(), true)
            );
        }

        $dup = sqlsrv_fetch_array($stmtCheckDuplicateItem, SQLSRV_FETCH_ASSOC);

        if ($dup) {
            throw new Exception(
                "Item sudah ada di line " . intval($dup["ORDP_LINO"]) .
                ". Tidak boleh input item yang sama dua kali."
            );
        }

        /*
            Cek line.
        */
        $sqlCheckLine = "
            SET NOCOUNT ON;

            SELECT TOP 1
                ORDR_ID,
                ORDP_LINO
            FROM dbo.ORDR_PAR
            WHERE ORDR_ID = ?
              AND ORDP_LINO = ?
        ";

        $stmtCheckLine = sqlsrv_query($conn, $sqlCheckLine, array(
            $ordr_id,
            $lino
        ));

        if ($stmtCheckLine === false) {
            throw new Exception(
                "Cek detail line " . $lino . " gagal: " .
                print_r(sqlsrv_errors(), true)
            );
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
                $priceId,
                $price,
                $qty,
                $dqty,
                $bqty,
                $closed,
                $remark,
                $ordr_id,
                $lino
            );

            $updated++;
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
                $lino,
                $priceId,
                $price,
                $qty,
                $dqty,
                $bqty,
                $closed,
                $remark
            );

            $inserted++;
        }

        $stmtSaveLine = sqlsrv_query($conn, $sqlSaveLine, $paramsLine);

        if ($stmtSaveLine === false) {
            throw new Exception(
                "Simpan detail line " . $lino . " gagal: " .
                print_r(sqlsrv_errors(), true)
            );
        }

        $saved++;
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success"  => true,
        "message"  => "Order berhasil disimpan. Detail: " . $saved,
        "ORDR_ID"  => $ordr_id,
        "saved"    => $saved,
        "inserted" => $inserted,
        "updated"  => $updated
    ));
    exit();

} catch (Exception $e) {
    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => "Simpan order gagal: " . $e->getMessage()
    ));
    exit();
}
?>