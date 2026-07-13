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

    if ($value == "" || $value === null) return "";

    $ts = strtotime((string)$value);
    if ($ts === false) return "";

    return date("d-M-Y", $ts);
}

function generate_quo_no($conn, $quoDate) {
    $prefix = date("ymd", strtotime($quoDate));

    $sql = "
        SELECT TOP 1 QUO_NO
        FROM dbo.QUOTATION
        WHERE QUO_NO LIKE ?
        ORDER BY QUO_NO DESC
    ";

    $stmt = sqlsrv_query($conn, $sql, array($prefix . "%"));
    $next = 1;

    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if ($r && isset($r["QUO_NO"])) {
            $lastNo = trim((string)$r["QUO_NO"]);
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
   POPUP HISTORY QUOTATION ITEM
====================================================== */
if (getv("action", "") == "quo_history") {
    $itemId = intval(getv("item_id", "0"));

    $sqlHis = "
        SELECT TOP 200
            Q.QUO_NO,
            Q.QUO_DATE,
            Q.QUO_EFFDATE,
            Q.CURR_CODE,
            S.SUP_CODE,
            S.SUP_COMP,
            D.QUOD_PRICE,
            D.QUOD_MINQTY,
            D.QUOD_UNIT,
            D.QUOD_TERM,
            D.QUOD_ACTIVE
        FROM dbo.QUOT_DETAIL D
        INNER JOIN dbo.QUOTATION Q ON D.QUO_ID = Q.QUO_ID
        LEFT JOIN dbo.SUPPLIER S ON Q.SUP_ID = S.SUP_ID
        WHERE D.ITEM_ID = ?
        ORDER BY Q.QUO_DATE DESC, Q.QUO_NO DESC
    ";

    $stmtHis = sqlsrv_query($conn, $sqlHis, array($itemId));

    if ($stmtHis === false) {
        die("<pre>Query quotation history error:\n" . sql_error_text() . "</pre>");
    }
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation History</title>
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
            margin-top: 8px;
        }
    </style>
</head>
<body>

<div class="title">QUOTATION HISTORY - ITEM_ID : <?php echo h($itemId); ?></div>

<table>
    <thead>
        <tr>
            <th style="width:35px;">No</th>
            <th style="width:100px;">Quo No</th>
            <th style="width:90px;">Date</th>
            <th style="width:90px;">Eff Date</th>
            <th style="width:70px;">Curr</th>
            <th style="width:90px;">Sup.Code</th>
            <th>Supplier</th>
            <th style="width:80px;">Price</th>
            <th style="width:80px;">Min.Qty</th>
            <th style="width:70px;">Unit</th>
            <th style="width:80px;">Term</th>
            <th style="width:70px;">Active</th>
        </tr>
    </thead>
    <tbody>
        <?php $no = 1; ?>
        <?php while ($r = sqlsrv_fetch_array($stmtHis, SQLSRV_FETCH_ASSOC)) { ?>
            <tr>
                <td class="center"><?php echo h($no); ?></td>
                <td><?php echo h($r["QUO_NO"]); ?></td>
                <td><?php echo h(fmt_date_view($r["QUO_DATE"])); ?></td>
                <td><?php echo h(fmt_date_view($r["QUO_EFFDATE"])); ?></td>
                <td class="center"><?php echo h($r["CURR_CODE"]); ?></td>
                <td><?php echo h($r["SUP_CODE"]); ?></td>
                <td><?php echo h($r["SUP_COMP"]); ?></td>
                <td class="num"><?php echo h(number_format(floatval($r["QUOD_PRICE"]), 2, ".", ",")); ?></td>
                <td class="num"><?php echo h(number_format(floatval($r["QUOD_MINQTY"]), 2, ".", ",")); ?></td>
                <td><?php echo h($r["QUOD_UNIT"]); ?></td>
                <td><?php echo h($r["QUOD_TERM"]); ?></td>
                <td class="center"><?php echo intval($r["QUOD_ACTIVE"]) == 1 ? "YES" : ""; ?></td>
            </tr>
            <?php $no++; ?>
        <?php } ?>

        <?php if ($no == 1) { ?>
            <tr>
                <td colspan="12" class="center">History quotation tidak ditemukan.</td>
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
   DELETE QUOTATION HEADER
   Pengaman: kalau masih ada detail, header tidak boleh dihapus
====================================================== */
if ($action == "delete") {
    $quoId = intval(postv("quo_id", "0"));

    if ($quoId <= 0) {
        $error = "Pilih quotation dulu.";
    } else {
        // cek detail dulu
        $sqlCek = "
            SELECT COUNT(*) AS CNT
            FROM dbo.QUOT_DETAIL
            WHERE QUO_ID = ?
        ";

        $stmtCek = sqlsrv_query($conn, $sqlCek, array($quoId));

        if ($stmtCek === false) {
            $error = "Cek detail quotation gagal:\n" . sql_error_text();
        } else {
            $rCek = sqlsrv_fetch_array($stmtCek, SQLSRV_FETCH_ASSOC);
            $detailCount = intval($rCek["CNT"]);

            if ($detailCount > 0) {
                $error = "Tidak dapat menghapus, masih ada detail quotation.";
            } else {
                $sqlDel = "
                    DELETE FROM dbo.QUOTATION
                    WHERE QUO_ID = ?
                ";

                $stmtDel = sqlsrv_query($conn, $sqlDel, array($quoId));

                if ($stmtDel === false) {
                    $error = "Delete quotation gagal:\n" . sql_error_text();
                } else {
                    header("Location: quotation.php?msg=deleted");
                    exit;
                }
            }
        }
    }
}


/* ======================================================
   SAVE QUOTATION
====================================================== */
if ($action == "save") {
    $quoId       = intval(postv("quo_id", "0"));
    $supId       = intval(postv("sup_id", "0"));
    $quoNo       = strtoupper(postv("quo_no", ""));
    $quoDate     = postv("quo_date", date("Y-m-d"));
    $currCode    = strtoupper(postv("curr_code", "IDR"));
    $quoEffDate  = postv("quo_effdate", date("Y-m-d"));
    $allowEmpty  = intval(postv("allow_empty_detail", "0"));

    if ($quoDate == "") {
        $quoDate = date("Y-m-d");
    }

    if ($quoEffDate == "") {
        $quoEffDate = $quoDate;
    }

    if ($quoNo == "") {
        $quoNo = generate_quo_no($conn, $quoDate);
    }

    if ($supId <= 0) {
        $error = "Supplier wajib dipilih.";
    } else {
        $itemIds    = isset($_POST["item_id"]) ? $_POST["item_id"] : array();
        $itemCodes  = isset($_POST["item_code"]) ? $_POST["item_code"] : array();
        $prices     = isset($_POST["quod_price"]) ? $_POST["quod_price"] : array();
        $minQtys    = isset($_POST["quod_minqty"]) ? $_POST["quod_minqty"] : array();
        $units      = isset($_POST["quod_unit"]) ? $_POST["quod_unit"] : array();
        $terms      = isset($_POST["quod_term"]) ? $_POST["quod_term"] : array();
        $actives    = isset($_POST["quod_active"]) ? $_POST["quod_active"] : array();

        $hasDetail = false;

        for ($i = 0; $i < count($itemIds); $i++) {
            $itemId = intval($itemIds[$i]);
            $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";

            if ($itemId <= 0 && $itemCode != "") {
                $itemId = find_item_id_by_code($conn, $itemCode);
            }

            $price = isset($prices[$i]) ? to_float($prices[$i]) : 0;

            if ($itemId > 0 && $price > 0) {
                $hasDetail = true;
                break;
            }
        }

        if (!$hasDetail && !($allowEmpty == 1 && $quoId > 0)) {
            $error = "Detail quotation minimal 1 baris dan price harus lebih dari 0.";
        } else {
            sqlsrv_begin_transaction($conn);
            $ok = true;

            if ($quoId > 0) {
                $sqlH = "
                    UPDATE dbo.QUOTATION SET
                        SUP_ID = ?,
                        QUO_NO = ?,
                        QUO_DATE = ?,
                        CURR_CODE = ?,
                        QUO_EFFDATE = ?
                    WHERE QUO_ID = ?
                ";

                $paramsH = array(
                    $supId,
                    $quoNo,
                    $quoDate,
                    $currCode,
                    $quoEffDate,
                    $quoId
                );

                $stmtH = sqlsrv_query($conn, $sqlH, $paramsH);
                if ($stmtH === false) {
                    $ok = false;
                }
            } else {
                $sqlH = "
                    INSERT INTO dbo.QUOTATION
                    (
                        SUP_ID,
                        QUO_NO,
                        QUO_DATE,
                        CURR_CODE,
                        QUO_EFFDATE
                    )
                    OUTPUT INSERTED.QUO_ID
                    VALUES
                    (?, ?, ?, ?, ?)
                ";

                $paramsH = array(
                    $supId,
                    $quoNo,
                    $quoDate,
                    $currCode,
                    $quoEffDate
                );

                $stmtH = sqlsrv_query($conn, $sqlH, $paramsH);

                if ($stmtH === false) {
                    $ok = false;
                } else {
                    $newRow = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_NUMERIC);
                    if ($newRow) {
                        $quoId = intval($newRow[0]);
                    } else {
                        $ok = false;
                    }
                }
            }

            if ($ok) {
                $stmtDel = sqlsrv_query($conn, "DELETE FROM dbo.QUOT_DETAIL WHERE QUO_ID = ?", array($quoId));
                if ($stmtDel === false) {
                    $ok = false;
                }
            }

            if ($ok) {
                for ($i = 0; $i < count($itemIds); $i++) {
                    $itemId = intval($itemIds[$i]);
                    $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";

                    if ($itemId <= 0 && $itemCode != "") {
                        $itemId = find_item_id_by_code($conn, $itemCode);
                    }

                    $price  = isset($prices[$i]) ? to_float($prices[$i]) : 0;
                    $minQty = isset($minQtys[$i]) ? to_float($minQtys[$i]) : 0;
                    $unit   = isset($units[$i]) ? trim((string)$units[$i]) : "";
                    $term   = isset($terms[$i]) ? trim((string)$terms[$i]) : "";
                    $active = isset($actives[$i]) ? 1 : 0;

                    if ($itemId <= 0 || $price <= 0) {
                        continue;
                    }

                    $sqlD = "
                        INSERT INTO dbo.QUOT_DETAIL
                        (
                            QUOD_PRICE,
                            QUOD_MINQTY,
                            QUOD_UNIT,
                            QUOD_TERM,
                            ITEM_ID,
                            QUO_ID,
                            QUOD_ACTIVE
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?)
                    ";

                    $paramsD = array(
                        $price,
                        $minQty,
                        $unit,
                        $term,
                        $itemId,
                        $quoId,
                        $active
                    );

                    $stmtD = sqlsrv_query($conn, $sqlD, $paramsD);

                    if ($stmtD === false) {
                        $ok = false;
                        break;
                    }
                }
            }

            if ($ok) {
                sqlsrv_commit($conn);
                header("Location: quotation.php?edit=" . $quoId . "&msg=saved");
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
    "QUO_ID" => "",
    "SUP_ID" => "",
    "SUP_CODE" => "",
    "SUP_COMP" => "",
    "QUO_NO" => "",
    "QUO_DATE" => date("Y-m-d"),
    "CURR_CODE" => "IDR",
    "QUO_EFFDATE" => date("Y-m-d")
);

$details = array();

if ($editId > 0) {
    $sqlH = "
        SELECT
            Q.QUO_ID,
            Q.SUP_ID,
            Q.QUO_NO,
            Q.QUO_DATE,
            Q.CURR_CODE,
            Q.QUO_EFFDATE,
            S.SUP_CODE,
            S.SUP_COMP
        FROM dbo.QUOTATION Q
        LEFT JOIN dbo.SUPPLIER S ON Q.SUP_ID = S.SUP_ID
        WHERE Q.QUO_ID = ?
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

            $header["QUO_DATE"] = fmt_date($header["QUO_DATE"]);
            $header["QUO_EFFDATE"] = fmt_date($header["QUO_EFFDATE"]);
        }
    }

    $sqlD = "
        SELECT
            D.QUOD_PRICE,
            D.QUOD_MINQTY,
            D.QUOD_UNIT,
            D.QUOD_TERM,
            D.ITEM_ID,
            D.QUO_ID,
            D.QUOD_ACTIVE,
            I.ITEM_CODE,
            I.ITEM_NAME,
            I.ITEM_UNIT
        FROM dbo.QUOT_DETAIL D
        LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID
        WHERE D.QUO_ID = ?
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
    $message = "Quotation berhasil disimpan.";
}

if (getv("msg", "") == "deleted") {
    $message = "Quotation berhasil dihapus.";
}

/* ======================================================
   SUPPLIER AUTO COMPLETE
====================================================== */
$supplierAuto = array();

$sqlSup = "
    SELECT TOP 1000
        SUP_ID,
        SUP_CODE,
        SUP_COMP,
        CURR_CODE
    FROM dbo.SUPPLIER
    WHERE ISNULL(SUP_CODE, '') <> ''
    ORDER BY SUP_CODE
";

$stmtSup = sqlsrv_query($conn, $sqlSup);

if ($stmtSup !== false) {
    while ($s = sqlsrv_fetch_array($stmtSup, SQLSRV_FETCH_ASSOC)) {
        $supplierAuto[] = array(
            "SUP_ID" => intval($s["SUP_ID"]),
            "SUP_CODE" => trim((string)$s["SUP_CODE"]),
            "SUP_COMP" => trim((string)$s["SUP_COMP"]),
            "CURR_CODE" => trim((string)$s["CURR_CODE"])
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
        ITEM_UNIT,
        ITEM_COST
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
            "ITEM_UNIT" => trim((string)$it["ITEM_UNIT"]),
            "ITEM_COST" => isset($it["ITEM_COST"]) ? floatval($it["ITEM_COST"]) : 0
        );
    }
}

/* ======================================================
   QUOTATION AUTO COMPLETE
====================================================== */
$quoAuto = array();

$sqlQuoAuto = "
    SELECT TOP 500
        Q.QUO_ID,
        Q.QUO_NO,
        Q.QUO_DATE,
        Q.CURR_CODE,
        S.SUP_CODE,
        S.SUP_COMP
    FROM dbo.QUOTATION Q
    LEFT JOIN dbo.SUPPLIER S ON Q.SUP_ID = S.SUP_ID
    ORDER BY Q.QUO_DATE DESC, Q.QUO_NO DESC
";

$stmtQuoAuto = sqlsrv_query($conn, $sqlQuoAuto);

if ($stmtQuoAuto !== false) {
    while ($qa = sqlsrv_fetch_array($stmtQuoAuto, SQLSRV_FETCH_ASSOC)) {
        $quoAuto[] = array(
            "QUO_ID" => intval($qa["QUO_ID"]),
            "QUO_NO" => trim((string)$qa["QUO_NO"]),
            "QUO_DATE" => fmt_date_view($qa["QUO_DATE"]),
            "CURR_CODE" => trim((string)$qa["CURR_CODE"]),
            "SUP_CODE" => trim((string)$qa["SUP_CODE"]),
            "SUP_COMP" => trim((string)$qa["SUP_COMP"])
        );
    }
}

/* ======================================================
   CURRENCY LIST
====================================================== */
$currList = array("IDR", "USD", "JPY");

$sqlCurr = "
    SELECT DISTINCT LTRIM(RTRIM(CURR_CODE)) AS CURR_CODE
    FROM dbo.CURR
    WHERE ISNULL(CURR_CODE, '') <> ''
    ORDER BY LTRIM(RTRIM(CURR_CODE))
";

$stmtCurr = @sqlsrv_query($conn, $sqlCurr);

if ($stmtCurr !== false) {
    $currList = array();

    while ($c = sqlsrv_fetch_array($stmtCurr, SQLSRV_FETCH_ASSOC)) {
        $v = trim((string)$c["CURR_CODE"]);
        if ($v != "") {
            $currList[] = $v;
        }
    }
}

/* ======================================================
   LIST QUOTATION
====================================================== */
$q = getv("q", "");

$where = "";
$paramsList = array();

if ($q != "") {
    $where = "
        WHERE Q.QUO_NO LIKE ?
           OR S.SUP_CODE LIKE ?
           OR S.SUP_COMP LIKE ?
    ";

    $paramsList[] = "%" . $q . "%";
    $paramsList[] = "%" . $q . "%";
    $paramsList[] = "%" . $q . "%";
}

$sqlList = "
    SELECT TOP 300
        Q.QUO_ID,
        Q.QUO_NO,
        Q.QUO_DATE,
        Q.QUO_EFFDATE,
        Q.CURR_CODE,
        S.SUP_CODE,
        S.SUP_COMP,
        COUNT(D.ITEM_ID) AS DETAIL_COUNT
    FROM dbo.QUOTATION Q
    LEFT JOIN dbo.SUPPLIER S ON Q.SUP_ID = S.SUP_ID
    LEFT JOIN dbo.QUOT_DETAIL D ON Q.QUO_ID = D.QUO_ID
    $where
    GROUP BY
        Q.QUO_ID,
        Q.QUO_NO,
        Q.QUO_DATE,
        Q.QUO_EFFDATE,
        Q.CURR_CODE,
        S.SUP_CODE,
        S.SUP_COMP
    ORDER BY Q.QUO_DATE DESC, Q.QUO_NO DESC
";

$stmtList = sqlsrv_query($conn, $sqlList, $paramsList);

if ($stmtList === false) {
    die("<pre>Query list quotation error:\n" . sql_error_text() . "</pre>");
}

if (count($details) == 0) {
    for ($i = 0; $i < 10; $i++) {
        $details[] = array(
            "ITEM_ID" => "",
            "ITEM_CODE" => "",
            "ITEM_NAME" => "",
            "ITEM_UNIT" => "",
            "QUOD_PRICE" => "",
            "QUOD_MINQTY" => "",
            "QUOD_UNIT" => "",
            "QUOD_TERM" => "",
            "QUOD_ACTIVE" => 0
        );
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation</title>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #7f8700;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #ffffff;
        }

        .wrap {
            padding: 8px 12px;
        }

        .page-title {
            background: #c5dfc5;
            color: #000000;
            text-align: center;
            font-size: 30px;
            line-height: 42px;
            height: 42px;
            margin: -8px -12px 8px -12px;
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

        .btn-os {
            background: #d9eaf7;
            width: 55px;
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
            width: 780px;
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

        .sup-code {
            width: 65px;
        }

        .company {
            width: 310px;
        }

        .quo-no {
            width: 95px;
        }

        .curr {
            width: 60px;
        }

        .date {
            width: 125px;
        }

        table.detail {
            width: 780px;
            border-collapse: collapse;
            background: #ffffff;
            color: #000000;
            margin-top: 16px;
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

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .action-cell {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 4px;
        }

        .search-area {
            margin-top: 10px;
        }

        .grid-wrap {
            width: 780px;
            height: 165px;
            overflow: auto;
            background: #ffffff;
            border: 1px solid #777777;
            margin-top: 8px;
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

        .bottom-buttons {
            margin-top: 10px;
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
        var supplierData = <?php echo json_encode($supplierAuto); ?>;
        var itemData = <?php echo json_encode($itemAuto); ?>;
        var quoData = <?php echo json_encode($quoAuto); ?>;

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

        function submitSave(allowEmpty) {
            if (byId("allow_empty_detail")) {
                byId("allow_empty_detail").value = allowEmpty ? "1" : "0";
            }

            var form = byId("quoForm");
            if (form) {
                form.submit();
            }
        }

        function newData() {
            window.location.href = "quotation.php";
        }

        function goEdit(id) {
            window.location.href = "quotation.php?edit=" + encodeURIComponent(id);
        }

       function confirmDelete() {
    return confirm("Yakin hapus quotation ini?");
}

        function deleteCurrent() {
            var id = byId("quo_id").value;

            if (id == "" || id == "0") {
                alert("Pilih quotation dulu.");
                return;
            }

            if (!confirmDelete()) return;

            byId("delete_quo_id").value = id;
            byId("deleteForm").submit();
        }

        function fillSupplier(s) {
            setValue("sup_id", s.SUP_ID);
            setValue("sup_code", s.SUP_CODE);
            setValue("sup_comp", s.SUP_COMP);

            if (s.CURR_CODE) {
                setValue("curr_code", s.CURR_CODE);
            }
        }

        function showSupplierAC(input) {
            initAC();

            var key = (input.value || "").toUpperCase();
            acMode = "supplier";
            acItems = [];
            acIndex = -1;

            if (key.length < 1) {
                hideAC();
                return;
            }

            for (var i = 0; i < supplierData.length; i++) {
                var s = supplierData[i];

                var text = (s.SUP_CODE || "") + " " + (s.SUP_COMP || "");

                if (text.toUpperCase().indexOf(key) >= 0) {
                    acItems.push(s);
                }

                if (acItems.length >= 30) break;
            }

            positionAC(input);

            renderAC(function (s) {
                return "<b>" + s.SUP_CODE + "</b> - " + s.SUP_COMP;
            }, pickSupplier);
        }

        function supplierKey(e, input) {
            if (e.key === "ArrowDown") {
                e.preventDefault();

                if (acMode !== "supplier" || acItems.length == 0) {
                    showSupplierAC(input);
                }

                acMove(1, function (s) {
                    return "<b>" + s.SUP_CODE + "</b> - " + s.SUP_COMP;
                }, pickSupplier);

                return false;
            }

            if (e.key === "ArrowUp") {
                e.preventDefault();

                if (acMode !== "supplier" || acItems.length == 0) {
                    showSupplierAC(input);
                }

                acMove(-1, function (s) {
                    return "<b>" + s.SUP_CODE + "</b> - " + s.SUP_COMP;
                }, pickSupplier);

                return false;
            }

            if (e.key === "Enter") {
                if (acMode === "supplier" && acItems.length > 0) {
                    e.preventDefault();
                    acEnter(pickSupplier);
                    return false;
                }

                e.preventDefault();
                submitSave(false);
                return false;
            }

            if (e.key === "Escape") {
                hideAC();
            }
        }

        function pickSupplier(s) {
            fillSupplier(s);
            hideAC();

            var quoNo = byId("quo_no");
            if (quoNo) {
                quoNo.focus();
                quoNo.select();
            }
        }

        function setRowItem(row, item) {
            setValue("item_id_" + row, item.ITEM_ID || "");
            setValue("item_code_" + row, item.ITEM_CODE || "");
            setValue("item_name_" + row, item.ITEM_NAME || "");
            setValue("quod_unit_" + row, item.ITEM_UNIT || "");

            var price = byId("quod_price_" + row);
            if (price && item.ITEM_COST && parseFloat(item.ITEM_COST) != 0) {
                price.value = item.ITEM_COST;
            }
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

            var price = byId("quod_price_" + row);
            if (price) {
                price.focus();
                price.select();
            }
        }

        function fieldEnterSave(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                submitSave(false);
                return false;
            }
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
                '<td><input type="text" name="quod_unit[]" id="quod_unit_' + row + '"></td>' +
                '<td><input type="text" name="quod_price[]" id="quod_price_' + row + '" class="num" onkeydown="fieldEnterSave(event)"></td>' +
                '<td><input type="text" name="quod_minqty[]" class="num" onkeydown="fieldEnterSave(event)"></td>' +
                '<td><input type="text" name="quod_term[]" onkeydown="fieldEnterSave(event)"></td>' +
                '<td class="center"><input type="checkbox" name="quod_active[' + row + ']" value="1"></td>' +
                '<td>' +
                    '<div class="action-cell">' +
                        '<button type="button" class="btn btn-os" onclick="showHistory(this)">OS/PO</button>' +
                        '<button type="button" class="btn btn-x" onclick="deleteRow(this)">X</button>' +
                    '</div>' +
                '</td>';

            tbody.appendChild(tr);
            renumberRows();

            byId("item_code_" + row).focus();
        }

        function deleteRow(btn) {
            var tr = btn.parentNode.parentNode.parentNode;

            if (!confirm("Hapus baris detail ini?")) {
                return;
            }

            tr.parentNode.removeChild(tr);
            renumberRows();

            var quoId = byId("quo_id").value;

            if (quoId != "" && quoId != "0") {
                submitSave(true);
            }
        }

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

        function showHistory(btn) {
            var tr = btn.parentNode.parentNode.parentNode;
            var itemInput = tr.querySelector('input[name="item_id[]"]');
            var itemId = itemInput ? itemInput.value : "";

            if (itemId == "" || itemId == "0") {
                alert("Pilih item dulu.");
                return;
            }

            var url = "quotation.php?action=quo_history&item_id=" + encodeURIComponent(itemId);
            window.open(url, "QUO_HISTORY", "width=980,height=420,scrollbars=yes,resizable=yes");
        }

        function showQuoAC(input) {
            initAC();

            var key = (input.value || "").toUpperCase();
            acMode = "quo";
            acItems = [];
            acIndex = -1;

            if (key.length < 1) {
                hideAC();
                return;
            }

            for (var i = 0; i < quoData.length; i++) {
                var q = quoData[i];

                var text =
                    (q.QUO_NO || "") + " " +
                    (q.QUO_DATE || "") + " " +
                    (q.SUP_CODE || "") + " " +
                    (q.SUP_COMP || "");

                if (text.toUpperCase().indexOf(key) >= 0) {
                    acItems.push(q);
                }

                if (acItems.length >= 30) break;
            }

            positionAC(input);

            renderAC(function (q) {
                return "<b>" + q.QUO_NO + "</b> - " +
                       q.QUO_DATE + " - " +
                       q.SUP_CODE + " " +
                       q.SUP_COMP;
            }, pickQuo);
        }

        function quoSearchKey(e, input) {
            if (e.key === "ArrowDown") {
                e.preventDefault();

                if (acMode !== "quo" || acItems.length == 0) {
                    showQuoAC(input);
                }

                acMove(1, function (q) {
                    return "<b>" + q.QUO_NO + "</b> - " +
                           q.QUO_DATE + " - " +
                           q.SUP_CODE + " " +
                           q.SUP_COMP;
                }, pickQuo);

                return false;
            }

            if (e.key === "ArrowUp") {
                e.preventDefault();

                if (acMode !== "quo" || acItems.length == 0) {
                    showQuoAC(input);
                }

                acMove(-1, function (q) {
                    return "<b>" + q.QUO_NO + "</b> - " +
                           q.QUO_DATE + " - " +
                           q.SUP_CODE + " " +
                           q.SUP_COMP;
                }, pickQuo);

                return false;
            }

            if (e.key === "Enter") {
                if (acMode === "quo" && acItems.length > 0) {
                    e.preventDefault();
                    acEnter(pickQuo);
                    return false;
                }

                return true;
            }

            if (e.key === "Escape") {
                hideAC();
            }
        }

        function pickQuo(q) {
            hideAC();
            window.location.href = "quotation.php?edit=" + encodeURIComponent(q.QUO_ID);
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

<div class="page-title">Quotation</div>

<div class="wrap">

    <?php if ($message != "") { ?>
        <div class="msg"><?php echo h($message); ?></div>
    <?php } ?>

    <?php if ($error != "") { ?>
        <div class="err"><?php echo h($error); ?></div>
    <?php } ?>

    <div class="top-buttons">
        <button type="button" class="btn" onclick="newData()">NEW</button>
        <button type="submit" form="quoForm" class="btn btn-save">SAVE QUOTATION</button>
        <button type="button" class="btn btn-del" onclick="deleteCurrent()">DELETE</button>
        <a href="dashboard_purch.php" class="btn">CLOSE</a>
    </div>

    <form id="quoForm" method="post" action="quotation.php">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="quo_id" id="quo_id" value="<?php echo h($header["QUO_ID"]); ?>">
        <input type="hidden" name="sup_id" id="sup_id" value="<?php echo h($header["SUP_ID"]); ?>">
        <input type="hidden" name="allow_empty_detail" id="allow_empty_detail" value="0">

        <table class="form-table">
            <tr>
                <td>
                    <span class="label">Supplier</span>
                    <input type="text"
                           name="sup_code"
                           id="sup_code"
                           class="sup-code"
                           value="<?php echo h($header["SUP_CODE"]); ?>"
                           autocomplete="off"
                           oninput="showSupplierAC(this)"
                           onkeydown="supplierKey(event, this)">
                </td>

                <td>
                    <span class="label">Company</span>
                    <input type="text"
                           name="sup_comp"
                           id="sup_comp"
                           class="company"
                           value="<?php echo h($header["SUP_COMP"]); ?>"
                           autocomplete="off"
                           oninput="showSupplierAC(this)"
                           onkeydown="supplierKey(event, this)">
                </td>
            </tr>

            <tr>
                <td>
                    <span class="label">No.</span>
                    <input type="text"
                           name="quo_no"
                           id="quo_no"
                           class="quo-no"
                           value="<?php echo h($header["QUO_NO"]); ?>"
                           placeholder="Auto"
                           onkeydown="headerKey(event)">
                </td>

                <td>
                    <span class="label">Curr.</span>
                    <select name="curr_code" id="curr_code" class="curr" onkeydown="headerKey(event)">
                        <?php foreach ($currList as $c) { ?>
                            <option value="<?php echo h($c); ?>" <?php echo trim((string)$header["CURR_CODE"]) == $c ? "selected" : ""; ?>>
                                <?php echo h($c); ?>
                            </option>
                        <?php } ?>
                    </select>
                </td>

                <td>
                    <span class="label">Effective Date</span>
                    <input type="date"
                           name="quo_date"
                           class="date"
                           value="<?php echo h($header["QUO_DATE"]); ?>"
                           onkeydown="headerKey(event)">
                </td>

                <td>
                    <span class="label">End Date</span>
                    <input type="date"
                           name="quo_effdate"
                           class="date"
                           value="<?php echo h($header["QUO_EFFDATE"]); ?>"
                           onkeydown="headerKey(event)">
                </td>
            </tr>
        </table>

        <table class="detail">
            <thead>
                <tr>
                    <th style="width:35px;">No</th>
                    <th style="width:90px;">Code</th>
                    <th>Item Name</th>
                    <th style="width:65px;">Unit</th>
                    <th style="width:80px;">Price</th>
                    <th style="width:80px;">Min.Qty</th>
                    <th style="width:80px;">Term</th>
                    <th style="width:75px;">Active</th>
                    <th style="width:85px;">OS/PO / X</th>
                </tr>
            </thead>

            <tbody id="detailBody">
                <?php for ($i = 0; $i < count($details); $i++) { ?>
                    <?php
                        $d = $details[$i];
                        $price = isset($d["QUOD_PRICE"]) ? floatval($d["QUOD_PRICE"]) : 0;
                        $minqty = isset($d["QUOD_MINQTY"]) ? floatval($d["QUOD_MINQTY"]) : 0;
                        $active = isset($d["QUOD_ACTIVE"]) ? intval($d["QUOD_ACTIVE"]) : 0;
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
                                   name="quod_unit[]"
                                   id="quod_unit_<?php echo h($i); ?>"
                                   value="<?php echo h(isset($d["QUOD_UNIT"]) ? $d["QUOD_UNIT"] : ""); ?>">
                        </td>

                        <td>
                            <input type="text"
                                   name="quod_price[]"
                                   id="quod_price_<?php echo h($i); ?>"
                                   class="num"
                                   value="<?php echo h($price == 0 ? "" : $price); ?>"
                                   onkeydown="fieldEnterSave(event)">
                        </td>

                        <td>
                            <input type="text"
                                   name="quod_minqty[]"
                                   class="num"
                                   value="<?php echo h($minqty == 0 ? "" : $minqty); ?>"
                                   onkeydown="fieldEnterSave(event)">
                        </td>

                        <td>
                            <input type="text"
                                   name="quod_term[]"
                                   value="<?php echo h(isset($d["QUOD_TERM"]) ? $d["QUOD_TERM"] : ""); ?>"
                                   onkeydown="fieldEnterSave(event)">
                        </td>

                        <td class="center">
                            <input type="checkbox"
                                   name="quod_active[<?php echo h($i); ?>]"
                                   value="1"
                                   <?php echo $active == 1 ? "checked" : ""; ?>>
                            Active
                        </td>

                        <td>
                            <div class="action-cell">
                                <button type="button" class="btn btn-os" onclick="showHistory(this)">OS/PO</button>
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

    <form id="deleteForm" method="post" action="quotation.php">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="quo_id" id="delete_quo_id" value="">
    </form>

    <div class="search-area">
        <form method="get" action="quotation.php">
            Search:
            <input type="text"
                   name="q"
                   id="quo_search"
                   value="<?php echo h($q); ?>"
                   style="width:300px;"
                   placeholder="Quotation No / Supplier"
                   autocomplete="off"
                   oninput="showQuoAC(this)"
                   onkeydown="quoSearchKey(event, this)">
            <button type="submit" class="btn">SEARCH</button>
            <a href="quotation.php" class="btn">ALL</a>
        </form>
    </div>

    <div class="grid-wrap">
        <table class="grid">
            <thead>
                <tr>
                    <th style="width:25px;"></th>
                    <th style="width:90px;">QUO_NO</th>
                    <th style="width:90px;">DATE</th>
                    <th style="width:90px;">END DATE</th>
                    <th style="width:70px;">CURR</th>
                    <th style="width:90px;">SUPPLIER</th>
                    <th>COMPANY</th>
                    <th style="width:70px;">DETAIL</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) { ?>
                    <tr onclick="goEdit('<?php echo h($r["QUO_ID"]); ?>')">
                        <td>▶</td>
                        <td><?php echo h($r["QUO_NO"]); ?></td>
                        <td><?php echo h(fmt_date_view($r["QUO_DATE"])); ?></td>
                        <td><?php echo h(fmt_date_view($r["QUO_EFFDATE"])); ?></td>
                        <td class="center"><?php echo h($r["CURR_CODE"]); ?></td>
                        <td><?php echo h($r["SUP_CODE"]); ?></td>
                        <td><?php echo h($r["SUP_COMP"]); ?></td>
                        <td class="num"><?php echo h(number_format(floatval($r["DETAIL_COUNT"]), 0, ".", ",")); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

</div>

<div id="acBox" class="ac-box"></div>

</body>
</html>