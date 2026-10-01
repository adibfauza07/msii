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

function month_roman($month) {
    $romans = array(
        1  => "I",
        2  => "II",
        3  => "III",
        4  => "IV",
        5  => "V",
        6  => "VI",
        7  => "VII",
        8  => "VIII",
        9  => "IX",
        10 => "X",
        11 => "XI",
        12 => "XII"
    );

    return isset($romans[$month]) ? $romans[$month] : "";
}

function is_special_customer($cust_id) {
    return ($cust_id == 234 || $cust_id == 399);
}

function get_next_ds_inv_no($conn, $cust_abbr, $date_text) {
    $ts = strtotime($date_text);

    if ($ts === false) {
        $ts = time();
    }

    $month = intval(date("n", $ts));
    $year2 = date("y", $ts);
    $monthRoman = month_roman($month);

    $pattern = "%/IMC/DS/" . $cust_abbr . "/%";

    $sql = "
        SELECT TOP 1 DI_DSNO
        FROM DI
        WHERE DI_DSNO LIKE ?
        ORDER BY DI_ID DESC
    ";

    $stmt = sqlsrv_query($conn, $sql, array($pattern));

    if ($stmt === false) {
        throw new Exception("Gagal mengambil nomor DS terakhir: " . print_r(sqlsrv_errors(), true));
    }

    $newNo = 1;

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $lastDSNO = trim($row["DI_DSNO"]);

        $pos = strpos($lastDSNO, "/IMC/");

        if ($pos !== false && $pos > 0) {
            $lastNo = intval(substr($lastDSNO, 0, $pos));
            $lastYear = substr($lastDSNO, -2);

            if ($lastYear == $year2) {
                if ($lastNo >= 1 && $lastNo < 999) {
                    $newNo = $lastNo + 1;
                } else {
                    $newNo = 1;
                }
            } else {
                $newNo = 1;
            }
        }
    }

    $dsno  = sprintf("%03d/IMC/DS/%s/%s/%s", $newNo, $cust_abbr, $monthRoman, $year2);
    $invno = str_replace("/DS/", "/INV/", $dsno);

    return array($dsno, $invno);
}

function get_special_ds_inv_no($cust_abbr, $date_text) {
    $ts = strtotime($date_text);

    if ($ts === false) {
        $ts = time();
    }

    $month = date("m", $ts);
    $year2 = date("y", $ts);

    $dsno  = $cust_abbr . "/" . $month . "/" . $year2;
    $invno = $dsno;

    return array($dsno, $invno);
}

function generate_ds_inv_no($conn, $cust_id, $cust_abbr, $di_start_date) {
    if (is_special_customer($cust_id)) {
        return get_special_ds_inv_no($cust_abbr, $di_start_date);
    }

    return get_next_ds_inv_no($conn, $cust_abbr, $di_start_date);
}

/*
    AMBIL POST
*/
$di_id          = post_value("DI_ID");
$di_no          = post_value("DI_NO");
$di_start_date  = post_value("DI_START_DATE");
$di_date        = post_value("DI_DATE");
$cust_code      = post_value("CUST_CODE");
$di_orderno     = post_value("DI_ORDERNO");

/*
    Nilai edit manual dari form.
    INSERT pertama tetap auto-generate.
    UPDATE berikutnya boleh simpan nilai manual ini.
*/
$di_dsno_post   = post_value("DI_DSNO");
$di_invno_post  = post_value("DI_INVNO");

if ($di_no == "") {
    json_error("DI NO wajib diisi.");
}

if (strlen($di_no) > 7) {
    json_error("DI NO maksimal 7 karakter, sesuai struktur tabel DI.");
}

if ($di_start_date == "") {
    json_error("START DATE wajib diisi.");
}

if ($di_date == "") {
    json_error("DI DATE wajib diisi.");
}

if ($cust_code == "") {
    json_error("Customer wajib dipilih.");
}

if (strlen($di_dsno_post) > 25) {
    json_error("DS NO maksimal 25 karakter.");
}

if (strlen($di_invno_post) > 25) {
    json_error("INV NO maksimal 25 karakter.");
}

if (strlen($di_orderno) > 25) {
    json_error("ORDER NO maksimal 25 karakter.");
}

/*
    AMBIL CUSTOMER
*/
$sqlCust = "
    SELECT TOP 1
        CUST_ID,
        CUST_CODE,
        CUST_COMP,
        CUST_ABBR
    FROM CUST
    WHERE CUST_CODE = ?
";

$stmtCust = sqlsrv_query($conn, $sqlCust, array($cust_code));

if ($stmtCust === false) {
    json_error("Query customer gagal: " . print_r(sqlsrv_errors(), true));
}

$cust = sqlsrv_fetch_array($stmtCust, SQLSRV_FETCH_ASSOC);

if (!$cust) {
    json_error("Customer tidak ditemukan di database.");
}

$cust_id   = intval($cust["CUST_ID"]);
$cust_comp = trim($cust["CUST_COMP"]);
$cust_abbr = trim($cust["CUST_ABBR"]);

if ($cust_abbr == "") {
    json_error("Customer abbreviation belum diisi di tabel CUST.");
}

/*
    SAVE HEADER
*/
$mode = "";
$di_dsno = "";
$di_invno = "";

sqlsrv_begin_transaction($conn);

try {

    if ($di_id == "") {

        /*
            INSERT BARU:
            Tetap AUTO GENERATE.
            Nilai manual DS/INV dari form tidak dipakai saat insert pertama.
        */
        $mode = "insert";

        list($di_dsno, $di_invno) = generate_ds_inv_no(
            $conn,
            $cust_id,
            $cust_abbr,
            $di_start_date
        );

        $sqlInsert = "
            SET NOCOUNT ON;

            DECLARE @NewID TABLE
            (
                DI_ID INT
            );

            INSERT INTO DI
            (
                DI_NO,
                CUST_ID,
                CUST_CODE,
                DI_START_DATE,
                DI_DATE,
                DI_INVNO,
                DI_DSNO,
                DI_ORDERNO
            )
            OUTPUT INSERTED.DI_ID INTO @NewID
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?
            );

            SELECT DI_ID FROM @NewID;
        ";

        $paramsInsert = array(
            $di_no,
            $cust_id,
            $cust_code,
            $di_start_date,
            $di_date,
            $di_invno,
            $di_dsno,
            $di_orderno
        );

        $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

        if ($stmtInsert === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        $rowInsert = sqlsrv_fetch_array($stmtInsert, SQLSRV_FETCH_ASSOC);

        if (!$rowInsert) {
            throw new Exception("Header tersimpan, tapi DI_ID baru gagal dibaca.");
        }

        $di_id = intval($rowInsert["DI_ID"]);

    } else {

        /*
            UPDATE:
            DS/INV bisa diedit manual.
            Kalau form DS/INV kosong, pakai nomor lama.
            Kalau customer berubah dan form kosong, generate ulang.
        */
        $mode = "update";
        $di_id_int = intval($di_id);

        $sqlOld = "
            SELECT TOP 1
                CUST_ID,
                DI_DSNO,
                DI_INVNO
            FROM DI
            WHERE DI_ID = ?
        ";

        $stmtOld = sqlsrv_query($conn, $sqlOld, array($di_id_int));

        if ($stmtOld === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        $old = sqlsrv_fetch_array($stmtOld, SQLSRV_FETCH_ASSOC);

        if (!$old) {
            throw new Exception("Data DI tidak ditemukan untuk update.");
        }

        $old_cust_id = intval($old["CUST_ID"]);
        $old_dsno    = trim($old["DI_DSNO"]);
        $old_invno   = trim($old["DI_INVNO"]);

        $auto_dsno = "";
        $auto_invno = "";

        if ($cust_id != $old_cust_id || $old_dsno == "" || $old_invno == "") {
            list($auto_dsno, $auto_invno) = generate_ds_inv_no(
                $conn,
                $cust_id,
                $cust_abbr,
                $di_start_date
            );
        }

        /*
            Ini inti perbaikannya:
            Kalau user edit DS/INV di form, simpan nilai itu.
        */
        if ($di_dsno_post != "") {
            $di_dsno = $di_dsno_post;
        } elseif ($old_dsno != "") {
            $di_dsno = $old_dsno;
        } else {
            $di_dsno = $auto_dsno;
        }

        if ($di_invno_post != "") {
            $di_invno = $di_invno_post;
        } elseif ($old_invno != "") {
            $di_invno = $old_invno;
        } else {
            $di_invno = $auto_invno;
        }

        $sqlUpdate = "
            UPDATE DI
            SET
                DI_NO = ?,
                CUST_ID = ?,
                CUST_CODE = ?,
                DI_START_DATE = ?,
                DI_DATE = ?,
                DI_INVNO = ?,
                DI_DSNO = ?,
                DI_ORDERNO = ?,
                DATE_UPDATED = GETDATE()
            WHERE DI_ID = ?
        ";

        $paramsUpdate = array(
            $di_no,
            $cust_id,
            $cust_code,
            $di_start_date,
            $di_date,
            $di_invno,
            $di_dsno,
            $di_orderno,
            $di_id_int
        );

        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

        if ($stmtUpdate === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        $di_id = $di_id_int;
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success"    => true,
        "mode"       => $mode,
        "message"    => "Header DI berhasil disimpan.",
        "DI_ID"      => $di_id,
        "DI_DSNO"    => $di_dsno,
        "DI_INVNO"   => $di_invno,
        "DI_ORDERNO" => $di_orderno,
        "CUST_ID"    => $cust_id,
        "CUST_COMP"  => $cust_comp,
        "CUST_ABBR"  => $cust_abbr
    ));
    exit();

} catch (Exception $e) {

    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => "Save header gagal: " . $e->getMessage()
    ));
    exit();
}
?>