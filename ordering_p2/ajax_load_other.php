<?php
require_once __DIR__ . "/../config/db_plant2.php";

header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Koneksi database gagal.",
        "rows" => array()
    ));
    exit();
}

function json_error($msg) {
    echo json_encode(array(
        "success" => false,
        "message" => $msg,
        "rows" => array()
    ));
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

function safe_trim($value) {
    if ($value === null) {
        return "";
    }

    return trim((string)$value);
}

function safe_int($value, $default = 0) {
    if ($value === null || $value === "") {
        return $default;
    }

    return intval($value);
}

function cut_text($value, $maxLen) {
    $value = trim((string)$value);

    if ($maxLen <= 0) {
        return $value;
    }

    if (strlen($value) > $maxLen) {
        return substr($value, 0, $maxLen);
    }

    return $value;
}

function normalize_date_112($dateText) {
    $dateText = trim($dateText);

    if ($dateText == "") {
        return "";
    }

    if (preg_match('/^\d{8}$/', $dateText)) {
        return $dateText;
    }

    $ts = strtotime($dateText);

    if ($ts === false) {
        return "";
    }

    return date("Ymd", $ts);
}

function get_first_value($row, $keys, $default = "") {
    for ($i = 0; $i < count($keys); $i++) {
        $key = $keys[$i];

        if (isset($row[$key]) && $row[$key] !== null && $row[$key] !== "") {
            return $row[$key];
        }
    }

    return $default;
}

function get_pack_code($conn, $pack_id) {
    static $cache = array();

    $pack_id = intval($pack_id);

    if ($pack_id <= 0) {
        return "";
    }

    if (isset($cache[$pack_id])) {
        return $cache[$pack_id];
    }

    $sql = "
        SELECT TOP 1
            ISNULL(PACK_CODE, '') AS PACK_CODE
        FROM dbo.PACK
        WHERE PACK_ID = ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($pack_id));

    if ($stmt === false) {
        $cache[$pack_id] = "";
        return "";
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if ($row) {
        $cache[$pack_id] = safe_trim($row["PACK_CODE"]);
    } else {
        $cache[$pack_id] = "";
    }

    return $cache[$pack_id];
}

function compare_load_other_rows($a, $b) {
    global $GLOBAL_HAS_SORT_NO;

    if ($GLOBAL_HAS_SORT_NO) {
        $sa = intval($a["SORT_NO"]);
        $sb = intval($b["SORT_NO"]);

        if ($sa <= 0) {
            $sa = 999999 + intval($a["SOURCE_NO"]);
        }

        if ($sb <= 0) {
            $sb = 999999 + intval($b["SOURCE_NO"]);
        }

        if ($sa == $sb) {
            return intval($a["SOURCE_NO"]) - intval($b["SOURCE_NO"]);
        }

        return $sa - $sb;
    }

    $ca = strtoupper((string)$a["CODE"]);
    $cb = strtoupper((string)$b["CODE"]);

    if ($ca == $cb) {
        return intval($a["SOURCE_NO"]) - intval($b["SOURCE_NO"]);
    }

    return strcmp($ca, $cb);
}

$di_id      = post_value("DI_ID");
$cust_code  = post_value("CUST_CODE");
$start_date = post_value("START_DATE");
$end_date   = post_value("END_DATE");

if ($di_id == "") {
    json_error("Save Header dulu sebelum LOAD DI OTHER.");
}

if ($cust_code == "") {
    json_error("Customer belum dipilih.");
}

if ($start_date == "") {
    json_error("Start date belum diisi.");
}

if ($end_date == "") {
    json_error("DI date belum diisi.");
}

$di_id_int = intval($di_id);

if ($di_id_int <= 0) {
    json_error("DI_ID tidak valid.");
}

$start_date_sql = normalize_date_112($start_date);
$end_date_sql   = normalize_date_112($end_date);

if ($start_date_sql == "") {
    json_error("Format Start Date tidak valid.");
}

if ($end_date_sql == "") {
    json_error("Format DI Date tidak valid.");
}

$sqlCheckDI = "
    SELECT TOP 1
        DI_ID
    FROM dbo.DI
    WHERE DI_ID = ?
";

$stmtCheckDI = sqlsrv_query($conn, $sqlCheckDI, array($di_id_int));

if ($stmtCheckDI === false) {
    json_error("Gagal cek DI_ID: " . print_r(sqlsrv_errors(), true));
}

if (!sqlsrv_fetch_array($stmtCheckDI, SQLSRV_FETCH_ASSOC)) {
    json_error("DI_ID tidak ditemukan. Save Header ulang.");
}

$sqlSource = "
    SET NOCOUNT ON;

    DECLARE @START_DATE DATETIME;
    DECLARE @END_DATE DATETIME;

    SET @START_DATE = CONVERT(DATETIME, ?, 112);
    SET @END_DATE   = CONVERT(DATETIME, ?, 112);

    EXECUTE dbo.SP_DI_PART ?, @START_DATE, @END_DATE;
";

$paramsSource = array(
    $start_date_sql,
    $end_date_sql,
    $cust_code
);

$stmtSource = sqlsrv_query($conn, $sqlSource, $paramsSource);

if ($stmtSource === false) {
    json_error(
        "Execute SP_DI_PART gagal.\n\n" .
        print_r(sqlsrv_errors(), true)
    );
}

$rawRows = array();
$sourceNo = 1;

do {
    while ($row = sqlsrv_fetch_array($stmtSource, SQLSRV_FETCH_ASSOC)) {

        $dailySch = safe_int(get_first_value($row, array(
            "DAILY_SCH",
            "DAILY_QTY",
            "PLAN_QTY",
            "SCH_QTY",
            "DIPA_QTY"
        ), 0), 0);

        $balQty = safe_int(get_first_value($row, array(
            "BAL_QTY",
            "BAL2",
            "B_QTY",
            "ORDP_BQTY"
        ), 0), 0);

        if ($dailySch > 0 && $balQty > 0) {
            if ($dailySch < $balQty) {
                $qtyToLoad = $dailySch;
            } else {
                $qtyToLoad = $balQty;
            }
        } elseif ($dailySch > 0) {
            $qtyToLoad = $dailySch;
        } elseif ($balQty > 0) {
            $qtyToLoad = $balQty;
        } else {
            $qtyToLoad = 0;
        }

        /*
            PENTING:
            DI_PART.PART_CODE hanya varchar(8).
            Manual form menyimpan CODE seperti 880001-1, bukan PART_NO / ITEM_NO panjang.
            Jadi prioritas CODE harus ITEM_CODE / PART_CODE / CODE.
            PART_NUM hanya dipakai fallback terakhir karena bisa panjang tergantung view.
        */
        $code = safe_trim(get_first_value($row, array(
            "ITEM_CODE",
            "PART_CODE",
            "CODE",
            "PART_NUM"
        ), ""));

        $partName = safe_trim(get_first_value($row, array(
            "ITEM_NAME",
            "PART_NAME",
            "NAME"
        ), ""));

        $partId = safe_int(get_first_value($row, array(
            "PART_ID",
            "ITEM_ID"
        ), 0), 0);

        $packId = safe_int(get_first_value($row, array(
            "PACK_ID"
        ), 1), 1);

        if ($packId <= 0) {
            $packId = 1;
        }

        $dipaPQty = safe_int(get_first_value($row, array(
            "DIPA_PQTY",
            "PACK_QTY",
            "P_QTY"
        ), 1), 1);

        if ($dipaPQty <= 0) {
            $dipaPQty = 1;
        }

        $priceId = safe_int(get_first_value($row, array(
            "PRICE_ID"
        ), 0), 0);

        $sortNo = safe_int(get_first_value($row, array(
            "DIPA_LINO",
            "ORDP_LINO",
            "LINO",
            "LINE_NO",
            "NO_URUT",
            "SORT_NO"
        ), 0), 0);

        $packCode = safe_trim(get_first_value($row, array(
            "DIPA_PACK",
            "PACK_DESC",
            "PACK_CODE"
        ), ""));

        if ($packCode == "") {
            $packCode = get_pack_code($conn, $packId);
        }

        $location = safe_trim(get_first_value($row, array(
            "LOCATION",
            "LOCA_CODE",
            "DIPA_LOCA"
        ), ""));

        $rawRows[] = array(
            "SOURCE_NO" => $sourceNo,
            "SORT_NO"   => $sortNo,
            "CODE"      => $code,
            "NAME"      => $partName,
            "PART_ID"   => $partId,
            "DIPA_QTY"  => $qtyToLoad,
            "PACK_ID"   => $packId,
            "DIPA_PQTY" => $dipaPQty,
            "PRICE_ID"  => $priceId,
            "PACK_DESC" => $packCode,
            "LOCATION"  => $location
        );

        $sourceNo++;
    }
} while (sqlsrv_next_result($stmtSource));

if (count($rawRows) == 0) {
    json_error(
        "Tidak ada data dari SP_DI_PART.\n\n" .
        "Parameter:\n" .
        "CUST_CODE: " . $cust_code . "\n" .
        "START_DATE: " . $start_date . "\n" .
        "END_DATE: " . $end_date
    );
}

$hasSortNo = false;

for ($i = 0; $i < count($rawRows); $i++) {
    if (intval($rawRows[$i]["SORT_NO"]) > 0) {
        $hasSortNo = true;
        break;
    }
}

$GLOBAL_HAS_SORT_NO = $hasSortNo;
usort($rawRows, "compare_load_other_rows");

$rows = array();

for ($i = 0; $i < count($rawRows); $i++) {
    $r = $rawRows[$i];

    $rows[] = array(
        "DIPA_LINO" => $i + 1,
        "CODE"      => cut_text($r["CODE"], 8),
        "NAME"      => $r["NAME"],
        "PART_ID"   => intval($r["PART_ID"]),
        "DIPA_QTY"  => intval($r["DIPA_QTY"]),
        "PACK_ID"   => intval($r["PACK_ID"]),
        "DIPA_PQTY" => intval($r["DIPA_PQTY"]),
        "PRICE_ID"  => intval($r["PRICE_ID"]),
        "PACK_DESC" => cut_text($r["PACK_DESC"], 10),
        "LOCATION"  => cut_text($r["LOCATION"], 20)
    );
}

if (!sqlsrv_begin_transaction($conn)) {
    json_error("Gagal mulai transaksi: " . print_r(sqlsrv_errors(), true));
}

try {

    $sqlDelete = "
        DELETE FROM dbo.DI_PART
        WHERE DI_ID = ?
    ";

    $stmtDelete = sqlsrv_query($conn, $sqlDelete, array($di_id_int));

    if ($stmtDelete === false) {
        throw new Exception(
            "Gagal hapus DI_PART lama: " .
            print_r(sqlsrv_errors(), true)
        );
    }

    /*
        Insert disamakan dengan manual dan struktur tabel DI_PART:
        PART_CODE varchar(8)  -> CODE manual
        SPR_CODE char(6)     -> kosong
        DIPA_PACK varchar(10)-> PACK_DESC manual / PACK_CODE
        BC_NO varchar(50)    -> kosong
        LOCATION varchar(20) -> LOCATION manual
    */
    $sqlInsert = "
        INSERT INTO dbo.DI_PART
        (
            DI_ID,
            DIPA_LINO,
            PART_CODE,
            PART_ID,
            SPR_CODE,
            DIPA_QTY,
            PACK_ID,
            DIPA_PACK,
            DIPA_PQTY,
            DIPA_POSTED,
            PRICE_ID,
            DIPA_CLOSE,
            BC_NO,
            LOCATION,
            BDQTY,
            IS_MANUAL,
            MANUAL_ORDR_ID,
            MANUAL_ORDP_LINO
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0, ?, ?, 0, 0, NULL, NULL
        )
    ";

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        if ($r["CODE"] == "") {
            throw new Exception(
                "PART_CODE kosong pada line " .
                $r["DIPA_LINO"]
            );
        }

        if (intval($r["PRICE_ID"]) <= 0) {
            throw new Exception(
                "PRICE_ID kosong pada line " .
                $r["DIPA_LINO"] .
                " / part " .
                $r["CODE"]
            );
        }

        $paramsInsert = array(
            $di_id_int,
            intval($r["DIPA_LINO"]),
            cut_text($r["CODE"], 8),
            intval($r["PART_ID"]),
            "",
            intval($r["DIPA_QTY"]),
            intval($r["PACK_ID"]),
            cut_text($r["PACK_DESC"], 10),
            intval($r["DIPA_PQTY"]),
            intval($r["PRICE_ID"]),
            "",
            cut_text($r["LOCATION"], 20)
        );

        $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

        if ($stmtInsert === false) {
            throw new Exception(
                "Gagal insert DI_PART line " .
                $r["DIPA_LINO"] .
                ": " .
                print_r(sqlsrv_errors(), true)
            );
        }
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success" => true,
        "message" => "LOAD DI OTHER selesai. Data dimuat: " . count($rows) . " item.",
        "rows" => $rows
    ));
    exit();

} catch (Exception $e) {

    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => "LOAD DI OTHER gagal: " . $e->getMessage(),
        "rows" => array()
    ));
    exit();
}
?>