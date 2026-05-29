<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/db_plant2.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function getv($name, $default = "") {
    return isset($_GET[$name]) ? trim((string)$_GET[$name]) : $default;
}

function postv($name, $default = "") {
    return isset($_POST[$name]) ? trim((string)$_POST[$name]) : $default;
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

function to_float($value) {
    $value = trim((string)$value);
    if ($value == "") return 0;
    return floatval(str_replace(",", "", $value));
}

function fmt_date($value) {
    if ($value instanceof DateTime) {
        return $value->format("Y-m-d");
    }

    if ($value == "" || $value === null) {
        return date("Y-m-d");
    }

    $ts = strtotime((string)$value);
    if ($ts === false) {
        return date("Y-m-d");
    }

    return date("Y-m-d", $ts);
}

function fmt_date_view($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-Y");
    }

    if ($value == "" || $value === null) {
        return "";
    }

    $ts = strtotime((string)$value);
    if ($ts === false) return "";

    return date("d-M-Y", $ts);
}

function generate_req_no($conn, $reqDate) {
    $prefix = "RQ" . date("ymd", strtotime($reqDate));

    $sql = "
        SELECT TOP 1 REQ_NO
        FROM dbo.REQUISITION
        WHERE REQ_NO LIKE ?
        ORDER BY REQ_NO DESC
    ";

    $stmt = sqlsrv_query($conn, $sql, array($prefix . "%"));
    $next = 1;

    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r && isset($r["REQ_NO"])) {
            $lastNo = trim((string)$r["REQ_NO"]);
            $lastSeq = intval(substr($lastNo, strlen($prefix)));
            $next = $lastSeq + 1;
        }
    }

    return $prefix . str_pad($next, 3, "0", STR_PAD_LEFT);
}

function find_item_id_by_code($conn, $itemCode) {
    $itemCode = trim((string)$itemCode);

    if ($itemCode == "") {
        return 0;
    }

    $sql = "
        SELECT TOP 1 ITEM_ID
        FROM dbo.ITEMS
        WHERE ITEM_CODE = ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($itemCode));

    if ($stmt === false) {
        return 0;
    }

    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$r) {
        return 0;
    }

    return intval($r["ITEM_ID"]);
}

/* ======================================================
   POPUP PO VIEW
   Tombol PO akan membuka halaman kecil ini
====================================================== */
if (getv("action", "") == "po_view") {
    $reqId  = intval(getv("req_id", "0"));
    $itemId = intval(getv("item_id", "0"));

    $sqlPO = "
        SELECT
            REQ_ID,
            ITEM_ID,
            PO_DATE AS [PO DATE],
            PO_NUM AS [PO #],
            POD_QTY AS [Qty],
            SUP_CODE AS [Sup.Code],
            SUP_COMP AS [Supplier]
        FROM dbo.REQ_ITEM_PO_VIEW
        WHERE REQ_ID = ?
          AND ITEM_ID = ?
        ORDER BY PO_DATE DESC, PO_NUM DESC
    ";

    $stmtPO = sqlsrv_query($conn, $sqlPO, array($reqId, $itemId));

    if ($stmtPO === false) {
        die("<pre>Query PO error:\n" . sql_error_text() . "</pre>");
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>PO List</title>
        <style>
            html, body {
                margin: 0;
                padding: 10px;
                background: #d4d0c8;
                font-family: Tahoma, Arial, sans-serif;
                font-size: 12px;
                color: #000000;
            }

            .title {
                background: #000080;
                color: #ffffff;
                padding: 6px 8px;
                font-weight: bold;
                margin-bottom: 8px;
            }

            table {
                border-collapse: collapse;
                width: 100%;
                background: #ffffff;
            }

            th {
                background: #1d2a3d;
                color: #ffffff;
                border: 1px solid #777777;
                padding: 5px;
                text-align: left;
            }

            td {
                border: 1px solid #cccccc;
                padding: 4px;
            }

            .num {
                text-align: right;
            }

            .center {
                text-align: center;
            }

            .btn {
                height: 24px;
                padding: 2px 12px;
                border: 1px solid #777777;
                background: #eeeeee;
                color: #000000;
                cursor: pointer;
                font-family: Tahoma, Arial, sans-serif;
                font-size: 12px;
                text-decoration: none;
                display: inline-block;
                line-height: 18px;
                box-sizing: border-box;
                margin-top: 8px;
            }
        </style>
    </head>
    <body>

    <div class="title">
        PO LIST - REQ_ID: <?php echo h($reqId); ?> / ITEM_ID: <?php echo h($itemId); ?>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:40px;">No</th>
                <th style="width:100px;">PO Date</th>
                <th style="width:120px;">PO #</th>
                <th style="width:90px;">Qty</th>
                <th style="width:90px;">Sup.Code</th>
                <th>Supplier</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; ?>
            <?php while ($p = sqlsrv_fetch_array($stmtPO, SQLSRV_FETCH_ASSOC)) { ?>
                <tr>
                    <td class="center"><?php echo h($no); ?></td>
                    <td><?php echo h(fmt_date_view($p["PO DATE"])); ?></td>
                    <td><?php echo h($p["PO #"]); ?></td>
                    <td class="num"><?php echo h(number_format(floatval($p["Qty"]), 2, ".", ",")); ?></td>
                    <td><?php echo h($p["Sup.Code"]); ?></td>
                    <td><?php echo h($p["Supplier"]); ?></td>
                </tr>
                <?php $no++; ?>
            <?php } ?>

            <?php if ($no == 1) { ?>
                <tr>
                    <td colspan="6" class="center">PO belum ada untuk item ini.</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <button type="button" class="btn" onclick="window.close()">CLOSE</button>

    </body>
    </html>
    <?php
    exit;
}

$message = "";
$error = "";

$action = postv("action", "");
$editId = intval(getv("edit", "0"));

/* ======================================================
   DELETE REQUISITION
====================================================== */
if ($action == "delete") {
    $reqId = intval(postv("req_id", "0"));

    if ($reqId <= 0) {
        $error = "Pilih requisition dulu.";
    } else {
        $sqlCek = "SELECT COUNT(*) AS CNT FROM dbo.REQ_DETAIL WHERE REQ_ID = ?";
        $stmtCek = sqlsrv_query($conn, $sqlCek, array($reqId));

        if ($stmtCek === false) {
            $error = "Cek detail gagal:\n" . sql_error_text();
        } else {
            $rc = sqlsrv_fetch_array($stmtCek, SQLSRV_FETCH_ASSOC);
            $cnt = intval($rc["CNT"]);

            if ($cnt > 0) {
                $error = "Tidak dapat menghapus, masih ada detail requisition.";
            } else {
                $sqlDel = "DELETE FROM dbo.REQUISITION WHERE REQ_ID = ?";
                $stmtDel = sqlsrv_query($conn, $sqlDel, array($reqId));

                if ($stmtDel === false) {
                    $error = "Delete gagal:\n" . sql_error_text();
                } else {
                    header("Location: requisition.php?msg=deleted");
                    exit;
                }
            }
        }
    }
}

/* ======================================================
   SAVE REQUISITION
====================================================== */
if ($action == "save") {
    $reqId    = intval(postv("req_id", "0"));
    $reqNo    = strtoupper(postv("req_no", ""));
    $reqDate  = postv("req_date", date("Y-m-d"));
    $reqDue   = postv("req_due", date("Y-m-d"));
    $reqRem   = postv("req_rem", "");
    $depId    = intval(postv("dep_id", "0"));
    $reqIsamr = isset($_POST["req_isamr"]) ? 1 : 0;
    $reqClose = isset($_POST["req_close"]) ? 1 : 0;
    $allowEmptyDetail = intval(postv("allow_empty_detail", "0"));
	
    if ($reqDate == "") $reqDate = date("Y-m-d");
    if ($reqDue == "")  $reqDue  = $reqDate;

    if ($reqNo == "") {
        $reqNo = generate_req_no($conn, $reqDate);
    }

    if ($depId <= 0) {
        $error = "Departement wajib dipilih.";
    } else {
        $itemIds   = isset($_POST["item_id"]) ? $_POST["item_id"] : array();
        $itemCodes = isset($_POST["item_code"]) ? $_POST["item_code"] : array();
        $qtys      = isset($_POST["reqd_qty"]) ? $_POST["reqd_qty"] : array();
        $rems      = isset($_POST["reqd_rem"]) ? $_POST["reqd_rem"] : array();

        $hasDetail = false;

        for ($i = 0; $i < count($itemIds); $i++) {
            $itemId = intval($itemIds[$i]);
            $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";

            if ($itemId <= 0 && $itemCode != "") {
                $itemId = find_item_id_by_code($conn, $itemCode);
            }

            $qty = isset($qtys[$i]) ? to_float($qtys[$i]) : 0;

            if ($itemId > 0 && $qty > 0) {
                $hasDetail = true;
                break;
            }
        }

        if (!$hasDetail && !($allowEmptyDetail == 1 && $reqId > 0)) {
    $error = "Detail item minimal 1 baris dan qty harus lebih dari 0.";
} else {
            sqlsrv_begin_transaction($conn);
            $ok = true;

            if ($reqId > 0) {
                $sqlH = "
                    UPDATE dbo.REQUISITION SET
                        REQ_NO = ?,
                        REQ_DATE = ?,
                        REQ_DUE = ?,
                        REQ_REM = ?,
                        REQ_ISAMR = ?,
                        REQ_CLOSE = ?,
                        DEP_ID = ?
                    WHERE REQ_ID = ?
                ";

                $paramsH = array(
                    $reqNo,
                    $reqDate,
                    $reqDue,
                    $reqRem,
                    $reqIsamr,
                    $reqClose,
                    $depId,
                    $reqId
                );

                $stmtH = sqlsrv_query($conn, $sqlH, $paramsH);
                if ($stmtH === false) $ok = false;
            } else {
                $sqlH = "
                    INSERT INTO dbo.REQUISITION
                    (
                        REQ_NO,
                        REQ_DATE,
                        REQ_DUE,
                        REQ_REM,
                        REQ_ISAMR,
                        REQ_CLOSE,
                        DEP_ID
                    )
                    OUTPUT INSERTED.REQ_ID
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?)
                ";

                $paramsH = array(
                    $reqNo,
                    $reqDate,
                    $reqDue,
                    $reqRem,
                    $reqIsamr,
                    $reqClose,
                    $depId
                );

                $stmtH = sqlsrv_query($conn, $sqlH, $paramsH);

                if ($stmtH === false) {
                    $ok = false;
                } else {
                    $newRow = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_NUMERIC);
                    if ($newRow) {
                        $reqId = intval($newRow[0]);
                    } else {
                        $ok = false;
                    }
                }
            }

            if ($ok) {
                $stmtDel = sqlsrv_query($conn, "DELETE FROM dbo.REQ_DETAIL WHERE REQ_ID = ?", array($reqId));
                if ($stmtDel === false) $ok = false;
            }

            if ($ok) {
                for ($i = 0; $i < count($itemIds); $i++) {
                    $itemId = intval($itemIds[$i]);
                    $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";

                    if ($itemId <= 0 && $itemCode != "") {
                        $itemId = find_item_id_by_code($conn, $itemCode);
                    }

                    $qty = isset($qtys[$i]) ? to_float($qtys[$i]) : 0;
                    $rem = isset($rems[$i]) ? trim((string)$rems[$i]) : "";

                    if ($itemId <= 0 || $qty <= 0) {
                        continue;
                    }

                    $sqlD = "
                        INSERT INTO dbo.REQ_DETAIL
                        (
                            ITEM_ID,
                            REQ_ID,
                            REQD_QTY,
                            REQD_REM
                        )
                        VALUES
                        (?, ?, ?, ?)
                    ";

                    $paramsD = array($itemId, $reqId, $qty, $rem);
                    $stmtD = sqlsrv_query($conn, $sqlD, $paramsD);

                    if ($stmtD === false) {
                        $ok = false;
                        break;
                    }
                }
            }

            if ($ok) {
                sqlsrv_commit($conn);
                header("Location: requisition.php?edit=" . $reqId . "&msg=saved");
                exit;
            } else {
                sqlsrv_rollback($conn);
                $error = "Simpan gagal:\n" . sql_error_text();
            }
        }
    }
}

/* ======================================================
   LOAD HEADER
====================================================== */
$header = array(
    "REQ_ID" => "",
    "REQ_NO" => "",
    "REQ_DATE" => date("Y-m-d"),
    "REQ_DUE" => date("Y-m-d"),
    "REQ_REM" => "",
    "REQ_ISAMR" => 0,
    "REQ_CLOSE" => 0,
    "DEP_ID" => ""
);

$details = array();

if ($editId > 0) {
    $sqlH = "
        SELECT
            REQ_ID,
            REQ_NO,
            REQ_DATE,
            REQ_DUE,
            REQ_REM,
            REQ_ISAMR,
            REQ_CLOSE,
            DEP_ID
        FROM dbo.REQUISITION
        WHERE REQ_ID = ?
    ";

    $stmtH = sqlsrv_query($conn, $sqlH, array($editId));

    if ($stmtH !== false) {
        $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);

        if ($rh) {
            foreach ($header as $k => $v) {
                if (isset($rh[$k])) {
                    $header[$k] = $rh[$k];
                }
            }

            $header["REQ_DATE"] = fmt_date($header["REQ_DATE"]);
            $header["REQ_DUE"] = fmt_date($header["REQ_DUE"]);
        }
    }

    $sqlD = "
        SELECT
            D.ITEM_ID,
            D.REQ_ID,
            D.REQD_QTY,
            D.REQD_REM,
            I.ITEM_CODE,
            I.ITEM_NAME,
            I.ITEM_UNIT
        FROM dbo.REQ_DETAIL D
        LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID
        WHERE D.REQ_ID = ?
        ORDER BY I.ITEM_CODE
    ";

    $stmtD = sqlsrv_query($conn, $sqlD, array($editId));

    if ($stmtD !== false) {
        while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
            $details[] = $rd;
        }
    }
}

/* ======================================================
   MESSAGE
====================================================== */
if (getv("msg", "") == "saved") {
    $message = "Requisition berhasil disimpan.";
}

if (getv("msg", "") == "deleted") {
    $message = "Requisition berhasil dihapus.";
}

/* ======================================================
   DEPT LIST
====================================================== */
$deptList = array();

$sqlDept = "
    SELECT DEP_ID, DEP_NAME, DEP_CODE
    FROM dbo.DEPT
    ORDER BY DEP_CODE
";

$stmtDept = sqlsrv_query($conn, $sqlDept);

if ($stmtDept !== false) {
    while ($d = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)) {
        $deptList[] = array(
            "DEP_ID" => intval($d["DEP_ID"]),
            "DEP_CODE" => trim((string)$d["DEP_CODE"]),
            "DEP_NAME" => trim((string)$d["DEP_NAME"])
        );
    }
}

/* ======================================================
   ITEM AUTO COMPLETE
====================================================== */
$itemAuto = array();

$sqlItem = "
    SELECT TOP 3000
        ITEM_ID,
        ITEM_CODE,
        ITEM_NAME,
        ITEM_UNIT
    FROM dbo.ITEMS
    WHERE ISNULL(ITEM_CODE, '') <> ''
      AND ISNULL(ITEM_INACTIVE, 0) = 0
    ORDER BY ITEM_CODE
";

$stmtItem = sqlsrv_query($conn, $sqlItem);

if ($stmtItem !== false) {
    while ($it = sqlsrv_fetch_array($stmtItem, SQLSRV_FETCH_ASSOC)) {
        $itemAuto[] = array(
            "ITEM_ID" => intval($it["ITEM_ID"]),
            "ITEM_CODE" => trim((string)$it["ITEM_CODE"]),
            "ITEM_NAME" => trim((string)$it["ITEM_NAME"]),
            "ITEM_UNIT" => trim((string)$it["ITEM_UNIT"])
        );
    }
}

/* ======================================================
   REQ AUTO COMPLETE
====================================================== */
$reqAuto = array();

$sqlReqAuto = "
    SELECT TOP 500
        R.REQ_ID,
        R.REQ_NO,
        R.REQ_DATE,
        R.REQ_REM,
        D.DEP_CODE,
        D.DEP_NAME
    FROM dbo.REQUISITION R
    LEFT JOIN dbo.DEPT D ON R.DEP_ID = D.DEP_ID
    ORDER BY R.REQ_DATE DESC, R.REQ_NO DESC
";

$stmtReqAuto = sqlsrv_query($conn, $sqlReqAuto);

if ($stmtReqAuto !== false) {
    while ($ra = sqlsrv_fetch_array($stmtReqAuto, SQLSRV_FETCH_ASSOC)) {
        $reqAuto[] = array(
            "REQ_ID"   => intval($ra["REQ_ID"]),
            "REQ_NO"   => trim((string)$ra["REQ_NO"]),
            "REQ_DATE" => fmt_date_view($ra["REQ_DATE"]),
            "REQ_REM"  => trim((string)$ra["REQ_REM"]),
            "DEP_CODE" => trim((string)$ra["DEP_CODE"]),
            "DEP_NAME" => trim((string)$ra["DEP_NAME"])
        );
    }
}

/* ======================================================
   LIST REQUISITION
====================================================== */
$q = getv("q", "");

$where = "";
$paramsList = array();

if ($q != "") {
    $where = "
        WHERE R.REQ_NO LIKE ?
           OR R.REQ_REM LIKE ?
           OR DPT.DEP_NAME LIKE ?
           OR DPT.DEP_CODE LIKE ?
    ";

    $paramsList[] = "%" . $q . "%";
    $paramsList[] = "%" . $q . "%";
    $paramsList[] = "%" . $q . "%";
    $paramsList[] = "%" . $q . "%";
}

$sqlList = "
    SELECT TOP 300
        R.REQ_ID,
        R.REQ_NO,
        R.REQ_DATE,
        R.REQ_DUE,
        R.REQ_REM,
        R.REQ_ISAMR,
        R.REQ_CLOSE,
        R.DEP_ID,
        DPT.DEP_CODE,
        DPT.DEP_NAME,
        COUNT(D.ITEM_ID) AS DETAIL_COUNT,
        ISNULL(SUM(D.REQD_QTY), 0) AS TOTAL_QTY
    FROM dbo.REQUISITION R
    LEFT JOIN dbo.DEPT DPT ON R.DEP_ID = DPT.DEP_ID
    LEFT JOIN dbo.REQ_DETAIL D ON R.REQ_ID = D.REQ_ID
    $where
    GROUP BY
        R.REQ_ID,
        R.REQ_NO,
        R.REQ_DATE,
        R.REQ_DUE,
        R.REQ_REM,
        R.REQ_ISAMR,
        R.REQ_CLOSE,
        R.DEP_ID,
        DPT.DEP_CODE,
        DPT.DEP_NAME
    ORDER BY R.REQ_DATE DESC, R.REQ_NO DESC
";

$stmtList = sqlsrv_query($conn, $sqlList, $paramsList);

if ($stmtList === false) {
    die("<pre>Query list requisition error:\n" . sql_error_text() . "</pre>");
}

if (count($details) == 0) {
    for ($i = 0; $i < 5; $i++) {
        $details[] = array(
            "ITEM_ID" => "",
            "ITEM_CODE" => "",
            "ITEM_NAME" => "",
            "ITEM_UNIT" => "",
            "REQD_QTY" => "",
            "REQD_REM" => ""
        );
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Requisition - Purchasing</title>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #0b8b80;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #ffffff;
        }

        .wrap {
            padding: 8px 10px;
        }

        .top-buttons {
            margin-bottom: 7px;
        }

        .btn {
            height: 24px;
            padding: 2px 12px;
            border: 1px solid #777777;
            background: #eeeeee;
            color: #000000;
            cursor: pointer;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            line-height: 18px;
            box-sizing: border-box;
        }

        .btn-save {
            background: #dff0d8;
        }

        .btn-del {
            background: #f2dede;
        }

        .btn-po {
            background: #d9eaf7;
            width: 32px;
            padding: 2px 4px;
        }

        .btn-x {
            background: #f2dede;
            width: 26px;
            padding: 2px 4px;
        }

        .btn:hover {
            background: #dcdcdc;
        }

        .msg {
            background: #dff0d8;
            color: #006100;
            border: 1px solid #6aa84f;
            padding: 6px;
            margin-bottom: 6px;
        }

        .err {
            background: #f2dede;
            color: #990000;
            border: 1px solid #cc0000;
            padding: 6px;
            margin-bottom: 6px;
            white-space: pre-wrap;
        }

        .label {
            display: block;
            color: #ffffff;
            font-weight: bold;
            margin-bottom: 2px;
        }

        table.form-table {
            border-collapse: collapse;
            width: 760px;
        }

        table.form-table td {
            padding: 3px 5px;
            vertical-align: top;
        }

        input[type="text"],
        input[type="date"],
        select {
            height: 23px;
            border: 1px solid #777777;
            padding: 2px 4px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            box-sizing: border-box;
            background: #ffffff;
            color: #000000;
        }

        .reqno {
            width: 125px;
        }

        .date {
            width: 125px;
        }

        .dept {
            width: 210px;
        }

        .rem {
            width: 420px;
        }

        table.detail {
            width: 760px;
            border-collapse: collapse;
            background: #ffffff;
            color: #000000;
            margin-top: 12px;
            margin-left: 5px;
        }

        table.detail th {
            background: #d9d9d9;
            color: #000000;
            border: 1px solid #888888;
            padding: 3px;
            text-align: left;
            font-weight: normal;
        }

        table.detail td {
            border: 1px solid #cccccc;
            padding: 2px;
        }

        table.detail input {
            width: 100%;
            height: 21px;
            border: none;
            padding: 2px;
            box-sizing: border-box;
        }

        table.detail input:focus {
            outline: 1px solid #2f65d9;
        }

        .action-cell {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 4px;
        }

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .grid-wrap {
            width: 760px;
            height: 180px;
            overflow: auto;
            background: #ffffff;
            border: 1px solid #777777;
            margin-top: 18px;
            margin-left: 5px;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            color: #000000;
        }

        table.grid th {
            background: #d9d9d9;
            color: #000000;
            border: 1px solid #888888;
            padding: 3px;
            text-align: left;
            font-weight: normal;
            white-space: nowrap;
        }

        table.grid td {
            border: 1px solid #cccccc;
            padding: 3px 4px;
            white-space: nowrap;
        }

        table.grid tr:hover {
            background: #cce5ff;
            cursor: pointer;
        }

        .search-area {
            margin-top: 10px;
            margin-left: 5px;
        }

        .bottom-buttons {
            margin-top: 12px;
            margin-left: 5px;
        }

        .ac-box {
            position: absolute;
            z-index: 9999;
            background: #ffffff;
            color: #000000;
            border: 1px solid #333333;
            max-height: 210px;
            overflow-y: auto;
            min-width: 280px;
            display: none;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.3);
        }

        .ac-item {
            padding: 4px 6px;
            cursor: pointer;
            border-bottom: 1px solid #dddddd;
        }

        .ac-item:hover,
        .ac-item.active {
            background: #2f65d9;
            color: #ffffff;
        }
    </style>

    <script>
        var itemData = <?php echo json_encode($itemAuto); ?>;
        var reqData = <?php echo json_encode($reqAuto); ?>;

        var acBox = null;
        var acItems = [];
        var acIndex = -1;
        var acMode = "";
        var acRow = -1;
        var rowSeq = <?php echo count($details); ?>;

        function byId(id) {
            return document.getElementById(id);
        }

        function setValue(id, value) {
            var el = byId(id);
            if (el) el.value = value == null ? "" : value;
        }

        function initAC() {
            acBox = document.getElementById("acBox");
        }

        function hideAC() {
            if (acBox) {
                acBox.style.display = "none";
                acBox.innerHTML = "";
            }

            acItems = [];
            acIndex = -1;
            acMode = "";
            acRow = -1;
        }

        function positionAC(input) {
            initAC();

            var rect = input.getBoundingClientRect();

            acBox.style.left = (rect.left + window.scrollX) + "px";
            acBox.style.top = (rect.bottom + window.scrollY) + "px";
            acBox.style.width = rect.width < 280 ? "280px" : rect.width + "px";
        }

        function renderAC(renderText, pickFunc) {
            initAC();

            acBox.innerHTML = "";

            for (var i = 0; i < acItems.length; i++) {
                var div = document.createElement("div");
                div.className = "ac-item" + (i == acIndex ? " active" : "");
                div.innerHTML = renderText(acItems[i]);
                div.setAttribute("data-index", i);

                div.onmousedown = function () {
                    var idx = parseInt(this.getAttribute("data-index"), 10);
                    pickFunc(acItems[idx]);
                };

                acBox.appendChild(div);
            }

            acBox.style.display = acItems.length > 0 ? "block" : "none";
        }

        function acMove(step, renderText, pickFunc) {
            if (acItems.length <= 0) return;

            acIndex += step;

            if (acIndex < 0) acIndex = acItems.length - 1;
            if (acIndex >= acItems.length) acIndex = 0;

            renderAC(renderText, pickFunc);
        }

        function acEnter(pickFunc) {
            if (acItems.length <= 0) return false;

            if (acIndex < 0) acIndex = 0;

            pickFunc(acItems[acIndex]);
            return true;
        }

       function submitSave(allowEmptyDetail) {
    var form = byId("reqForm");

    if (byId("allow_empty_detail")) {
        byId("allow_empty_detail").value = allowEmptyDetail ? "1" : "0";
    }

    if (form) {
        form.submit();
    }
}

        function newData() {
            window.location.href = "requisition.php";
        }

        function goEdit(id) {
            window.location.href = "requisition.php?edit=" + encodeURIComponent(id);
        }

        function confirmDelete() {
            return confirm("Yakin hapus requisition ini?");
        }

        function deleteCurrent() {
            var id = byId("req_id").value;

            if (id == "" || id == "0") {
                alert("Pilih requisition dulu.");
                return;
            }

            if (!confirmDelete()) return;

            byId("delete_req_id").value = id;
            byId("deleteForm").submit();
        }

        function findItemByCode(code) {
            code = (code || "").toUpperCase();

            for (var i = 0; i < itemData.length; i++) {
                if ((itemData[i].ITEM_CODE || "").toUpperCase() == code) {
                    return itemData[i];
                }
            }

            return null;
        }

        function findItemByName(name) {
            name = (name || "").toUpperCase();

            for (var i = 0; i < itemData.length; i++) {
                if ((itemData[i].ITEM_NAME || "").toUpperCase() == name) {
                    return itemData[i];
                }
            }

            return null;
        }

        function setRowItem(row, item) {
            setValue("item_id_" + row, item.ITEM_ID || "");
            setValue("item_code_" + row, item.ITEM_CODE || "");
            setValue("item_name_" + row, item.ITEM_NAME || "");
            setValue("item_unit_" + row, item.ITEM_UNIT || "");
        }

        function showItemAC(input, row) {
            initAC();

            var key = (input.value || "").toUpperCase();
            acMode = "item";
            acRow = row;
            acItems = [];
            acIndex = -1;

            if (key.length < 1) {
                hideAC();
                return;
            }

            for (var i = 0; i < itemData.length; i++) {
                var item = itemData[i];

                var code = (item.ITEM_CODE || "").toUpperCase();
                var name = (item.ITEM_NAME || "").toUpperCase();

                if (code.indexOf(key) >= 0 || name.indexOf(key) >= 0) {
                    acItems.push(item);
                }

                if (acItems.length >= 40) break;
            }

            positionAC(input);

            renderAC(function (item) {
                return "<b>" + item.ITEM_CODE + "</b> - " + item.ITEM_NAME;
            }, pickItem);
        }

        function itemKey(e, input, row) {
            if (e.key === "ArrowDown") {
                e.preventDefault();

                if (acMode !== "item" || acItems.length == 0) {
                    showItemAC(input, row);
                }

                acMove(1, function (item) {
                    return "<b>" + item.ITEM_CODE + "</b> - " + item.ITEM_NAME;
                }, pickItem);

                return false;
            }

            if (e.key === "ArrowUp") {
                e.preventDefault();

                if (acMode !== "item" || acItems.length == 0) {
                    showItemAC(input, row);
                }

                acMove(-1, function (item) {
                    return "<b>" + item.ITEM_CODE + "</b> - " + item.ITEM_NAME;
                }, pickItem);

                return false;
            }

            if (e.key === "Enter") {
                if (acMode === "item" && acItems.length > 0) {
                    e.preventDefault();
                    acEnter(pickItem);
                    return false;
                }

                e.preventDefault();

                var item = null;

                if (input.id.indexOf("item_code_") === 0) {
                    item = findItemByCode(input.value);
                } else {
                    item = findItemByName(input.value);
                }

                if (item) {
                    setRowItem(row, item);
                    var qty = byId("reqd_qty_" + row);
                    if (qty) {
                        qty.focus();
                        qty.select();
                    }
                }

                return false;
            }

            if (e.key === "Escape") {
                hideAC();
            }
        }

        function pickItem(item) {
            var row = acRow;

            setRowItem(row, item);
            hideAC();

            var qty = byId("reqd_qty_" + row);
            if (qty) {
                qty.focus();
                qty.select();
            }
        }

        function qtyKey(e, input) {
            if (e.key === "Enter") {
                e.preventDefault();

                if (input && input.value != "") {
                    submitSave(false);
                }

                return false;
            }

            if (e.key === "ArrowDown") {
                e.preventDefault();

                var qtys = document.getElementsByClassName("qty-input");
                for (var i = 0; i < qtys.length; i++) {
                    if (qtys[i] === input && qtys[i + 1]) {
                        qtys[i + 1].focus();
                        qtys[i + 1].select();
                        break;
                    }
                }
            }

            if (e.key === "ArrowUp") {
                e.preventDefault();

                var qtys2 = document.getElementsByClassName("qty-input");
                for (var j = 0; j < qtys2.length; j++) {
                    if (qtys2[j] === input && qtys2[j - 1]) {
                        qtys2[j - 1].focus();
                        qtys2[j - 1].select();
                        break;
                    }
                }
            }
        }

        function showReqAC(input) {
            initAC();

            var key = (input.value || "").toUpperCase();
            acMode = "req";
            acItems = [];
            acIndex = -1;

            if (key.length < 1) {
                hideAC();
                return;
            }

            for (var i = 0; i < reqData.length; i++) {
                var r = reqData[i];

                var text =
                    (r.REQ_NO || "") + " " +
                    (r.REQ_DATE || "") + " " +
                    (r.DEP_CODE || "") + " " +
                    (r.DEP_NAME || "") + " " +
                    (r.REQ_REM || "");

                if (text.toUpperCase().indexOf(key) >= 0) {
                    acItems.push(r);
                }

                if (acItems.length >= 30) break;
            }

            positionAC(input);

            renderAC(function (r) {
                return "<b>" + r.REQ_NO + "</b> - " +
                       r.REQ_DATE + " - " +
                       r.DEP_CODE + " " +
                       r.DEP_NAME + " - " +
                       r.REQ_REM;
            }, pickReq);
        }

        function reqSearchKey(e, input) {
            if (e.key === "ArrowDown") {
                e.preventDefault();

                if (acMode !== "req" || acItems.length == 0) {
                    showReqAC(input);
                }

                acMove(1, function (r) {
                    return "<b>" + r.REQ_NO + "</b> - " +
                           r.REQ_DATE + " - " +
                           r.DEP_CODE + " " +
                           r.DEP_NAME + " - " +
                           r.REQ_REM;
                }, pickReq);

                return false;
            }

            if (e.key === "ArrowUp") {
                e.preventDefault();

                if (acMode !== "req" || acItems.length == 0) {
                    showReqAC(input);
                }

                acMove(-1, function (r) {
                    return "<b>" + r.REQ_NO + "</b> - " +
                           r.REQ_DATE + " - " +
                           r.DEP_CODE + " " +
                           r.DEP_NAME + " - " +
                           r.REQ_REM;
                }, pickReq);

                return false;
            }

            if (e.key === "Enter") {
                if (acMode === "req" && acItems.length > 0) {
                    e.preventDefault();
                    acEnter(pickReq);
                    return false;
                }

                return true;
            }

            if (e.key === "Escape") {
                hideAC();
            }
        }

        function pickReq(r) {
            hideAC();
            window.location.href = "requisition.php?edit=" + encodeURIComponent(r.REQ_ID);
        }

        function headerKey(e) {
            if (e.key === "Enter") {
                if (acMode !== "" && acItems.length > 0) {
                    return true;
                }

                e.preventDefault();
                submitSave(false);
                return false;
            }
        }

        function addRow() {
            var tbody = byId("detailBody");
            var row = rowSeq;
            rowSeq++;

            var tr = document.createElement("tr");

            tr.innerHTML =
                '<td class="center row-no"></td>' +
                '<td>' +
                    '<input type="hidden" name="item_id[]" id="item_id_' + row + '">' +
                    '<input type="text" name="item_code[]" id="item_code_' + row + '" autocomplete="off" oninput="showItemAC(this, ' + row + ')" onkeydown="itemKey(event, this, ' + row + ')">' +
                '</td>' +
                '<td>' +
                    '<input type="text" name="item_name[]" id="item_name_' + row + '" autocomplete="off" oninput="showItemAC(this, ' + row + ')" onkeydown="itemKey(event, this, ' + row + ')">' +
                '</td>' +
                '<td><input type="text" name="item_unit[]" id="item_unit_' + row + '" readonly></td>' +
                '<td><input type="text" name="reqd_qty[]" id="reqd_qty_' + row + '" class="num qty-input" onkeydown="qtyKey(event, this)"></td>' +
                '<td><input type="text" name="reqd_rem[]" onkeydown="headerKey(event)"></td>' +
                '<td>' +
                    '<div class="action-cell">' +
                        '<button type="button" class="btn btn-po" onclick="showPO(this)">PO</button>' +
                        '<button type="button" class="btn btn-x" onclick="deleteRow(this)">X</button>' +
                    '</div>' +
                '</td>';

            tbody.appendChild(tr);
            renumberRows();

            byId("item_code_" + row).focus();
        }

       function deleteRow(btn) {
    var tr = btn.parentNode.parentNode.parentNode;
    var reqId = byId("req_id").value;

    if (!confirm("Hapus baris detail ini?")) {
        return;
    }

    tr.parentNode.removeChild(tr);
    renumberRows();

    if (reqId != "" && reqId != "0") {
        submitSave(true);
    }
}
-
        function renumberRows() {
            var rows = byId("detailBody").getElementsByTagName("tr");

            for (var i = 0; i < rows.length; i++) {
                var noCell = rows[i].getElementsByClassName("row-no")[0];
                if (noCell) {
                    noCell.innerHTML = i + 1;
                } else {
                    rows[i].cells[0].innerHTML = i + 1;
                }
            }
        }

        function showPO(btn) {
            var tr = btn.parentNode.parentNode.parentNode;
            var reqId = byId("req_id").value;
            var itemInput = tr.querySelector('input[name="item_id[]"]');
            var itemId = itemInput ? itemInput.value : "";

            if (reqId == "" || reqId == "0") {
                alert("Simpan / pilih requisition dulu.");
                return;
            }

            if (itemId == "" || itemId == "0") {
                alert("Pilih item dulu.");
                return;
            }

            var url = "requisition.php?action=po_view&req_id=" + encodeURIComponent(reqId) + "&item_id=" + encodeURIComponent(itemId);
            window.open(url, "PO_LIST", "width=760,height=420,scrollbars=yes,resizable=yes");
        }

        document.addEventListener("click", function (e) {
            initAC();
            if (acBox && !acBox.contains(e.target)) {
                if (!e.target || !e.target.getAttribute || e.target.getAttribute("autocomplete") !== "off") {
                    hideAC();
                }
            }
        });
    </script>
</head>
<body>

<div class="wrap">

    <?php if ($message != "") { ?>
        <div class="msg"><?php echo h($message); ?></div>
    <?php } ?>

    <?php if ($error != "") { ?>
        <div class="err"><?php echo h($error); ?></div>
    <?php } ?>

    <div class="top-buttons">
        <button type="button" class="btn" onclick="newData()">NEW</button>
        <button type="submit" form="reqForm" class="btn btn-save">SAVE REQUISITION</button>
        <button type="button" class="btn btn-del" onclick="deleteCurrent()">DELETE</button>
        <a href="dashboard_purchasing.php" class="btn">CLOSE</a>
    </div>

    <form id="reqForm" method="post" action="requisition.php">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="req_id" id="req_id" value="<?php echo h($header["REQ_ID"]); ?>">
		<input type="hidden" name="allow_empty_detail" id="allow_empty_detail" value="0">

        <table class="form-table">
            <tr>
                <td>
                    <span class="label">REQ NO</span>
                    <input type="text"
                           name="req_no"
                           class="reqno"
                           value="<?php echo h($header["REQ_NO"]); ?>"
                           placeholder="Auto"
                           onkeydown="headerKey(event)">
                </td>

                <td>
                    <span class="label">REQ DATE</span>
                    <input type="date"
                           name="req_date"
                           class="date"
                           value="<?php echo h($header["REQ_DATE"]); ?>"
                           onkeydown="headerKey(event)">
                </td>

                <td>
                    <span class="label">REQ DUE</span>
                    <input type="date"
                           name="req_due"
                           class="date"
                           value="<?php echo h($header["REQ_DUE"]); ?>"
                           onkeydown="headerKey(event)">
                </td>

                <td>
                    <span class="label">DEPT</span>
                    <select name="dep_id" class="dept" onkeydown="headerKey(event)">
                        <option value="">-- Pilih Dept --</option>
                        <?php foreach ($deptList as $d) { ?>
                            <option value="<?php echo h($d["DEP_ID"]); ?>" <?php echo intval($header["DEP_ID"]) == intval($d["DEP_ID"]) ? "selected" : ""; ?>>
                                <?php echo h($d["DEP_CODE"] . " - " . $d["DEP_NAME"]); ?>
                            </option>
                        <?php } ?>
                    </select>
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    <span class="label">REQ REMARK</span>
                    <input type="text"
                           name="req_rem"
                           class="rem"
                           value="<?php echo h($header["REQ_REM"]); ?>"
                           onkeydown="headerKey(event)">
                </td>

                <td>
                    <span class="label">&nbsp;</span>
                    <label>
                        <input type="checkbox" name="req_isamr" value="1" <?php echo intval($header["REQ_ISAMR"]) == 1 ? "checked" : ""; ?>>
                        Is AMR
                    </label>
                    &nbsp;&nbsp;
                    <label>
                        <input type="checkbox" name="req_close" value="1" <?php echo intval($header["REQ_CLOSE"]) == 1 ? "checked" : ""; ?>>
                        Close
                    </label>
                </td>
            </tr>
        </table>

        <table class="detail">
            <thead>
                <tr>
                    <th style="width:35px;">No</th>
                    <th style="width:120px;">ITEM CODE</th>
                    <th>ITEM NAME</th>
                    <th style="width:80px;">UNIT</th>
                    <th style="width:90px;">QTY</th>
                    <th style="width:170px;">REMARK</th>
                    <th style="width:75px;">PO / X</th>
                </tr>
            </thead>

            <tbody id="detailBody">
                <?php for ($i = 0; $i < count($details); $i++) { ?>
                    <?php
                        $d = $details[$i];
                        $qty = isset($d["REQD_QTY"]) ? floatval($d["REQD_QTY"]) : 0;
                    ?>
                    <tr>
                        <td class="center row-no"><?php echo h($i + 1); ?></td>

                        <td>
                            <input type="hidden" name="item_id[]" id="item_id_<?php echo h($i); ?>" value="<?php echo h($d["ITEM_ID"]); ?>">
                            <input type="text"
                                   name="item_code[]"
                                   id="item_code_<?php echo h($i); ?>"
                                   value="<?php echo h(isset($d["ITEM_CODE"]) ? $d["ITEM_CODE"] : ""); ?>"
                                   autocomplete="off"
                                   oninput="showItemAC(this, <?php echo h($i); ?>)"
                                   onkeydown="itemKey(event, this, <?php echo h($i); ?>)">
                        </td>

                        <td>
                            <input type="text"
                                   name="item_name[]"
                                   id="item_name_<?php echo h($i); ?>"
                                   value="<?php echo h(isset($d["ITEM_NAME"]) ? $d["ITEM_NAME"] : ""); ?>"
                                   autocomplete="off"
                                   oninput="showItemAC(this, <?php echo h($i); ?>)"
                                   onkeydown="itemKey(event, this, <?php echo h($i); ?>)">
                        </td>

                        <td>
                            <input type="text"
                                   name="item_unit[]"
                                   id="item_unit_<?php echo h($i); ?>"
                                   value="<?php echo h(isset($d["ITEM_UNIT"]) ? $d["ITEM_UNIT"] : ""); ?>"
                                   readonly>
                        </td>

                        <td>
                            <input type="text"
                                   name="reqd_qty[]"
                                   id="reqd_qty_<?php echo h($i); ?>"
                                   class="num qty-input"
                                   value="<?php echo h($qty == 0 ? "" : $qty); ?>"
                                   onkeydown="qtyKey(event, this)">
                        </td>

                        <td>
                            <input type="text"
                                   name="reqd_rem[]"
                                   value="<?php echo h(isset($d["REQD_REM"]) ? $d["REQD_REM"] : ""); ?>"
                                   onkeydown="headerKey(event)">
                        </td>

                        <td>
                            <div class="action-cell">
                                <button type="button" class="btn btn-po" onclick="showPO(this)">PO</button>
                                <button type="button" class="btn btn-x" onclick="deleteRow(this)">X</button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <div class="bottom-buttons">
            <button type="button" class="btn" onclick="addRow()">+ Tambah Baris</button>
            <button type="submit" class="btn btn-save">✔ Simpan</button>
        </div>
    </form>

    <form id="deleteForm" method="post" action="requisition.php">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="req_id" id="delete_req_id" value="">
    </form>

    <div class="search-area">
        <form method="get" action="requisition.php">
            Search:
            <input type="text"
                   name="q"
                   id="req_search"
                   value="<?php echo h($q); ?>"
                   style="width:280px;"
                   placeholder="REQ No / Dept / Remark"
                   autocomplete="off"
                   oninput="showReqAC(this)"
                   onkeydown="reqSearchKey(event, this)">
            <button type="submit" class="btn">SEARCH</button>
            <a href="requisition.php" class="btn">ALL</a>
        </form>
    </div>

    <div class="grid-wrap">
        <table class="grid">
            <thead>
                <tr>
                    <th style="width:25px;"></th>
                    <th style="width:100px;">REQ_NO</th>
                    <th style="width:90px;">REQ_DATE</th>
                    <th style="width:90px;">REQ_DUE</th>
                    <th style="width:120px;">DEPT</th>
                    <th>REQ_REM</th>
                    <th style="width:65px;">AMR</th>
                    <th style="width:65px;">CLOSE</th>
                    <th style="width:70px;">DETAIL</th>
                    <th style="width:80px;">QTY</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) { ?>
                    <tr onclick="goEdit('<?php echo h($r["REQ_ID"]); ?>')">
                        <td>▶</td>
                        <td><?php echo h($r["REQ_NO"]); ?></td>
                        <td><?php echo h(fmt_date_view($r["REQ_DATE"])); ?></td>
                        <td><?php echo h(fmt_date_view($r["REQ_DUE"])); ?></td>
                        <td><?php echo h(trim((string)$r["DEP_CODE"]) . " " . trim((string)$r["DEP_NAME"])); ?></td>
                        <td><?php echo h($r["REQ_REM"]); ?></td>
                        <td class="center"><?php echo intval($r["REQ_ISAMR"]) == 1 ? "YES" : ""; ?></td>
                        <td class="center"><?php echo intval($r["REQ_CLOSE"]) == 1 ? "YES" : ""; ?></td>
                        <td class="num"><?php echo h(number_format(floatval($r["DETAIL_COUNT"]), 0, ".", ",")); ?></td>
                        <td class="num"><?php echo h(number_format(floatval($r["TOTAL_QTY"]), 2, ".", ",")); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

</div>

<div id="acBox" class="ac-box"></div>

</body>
</html>