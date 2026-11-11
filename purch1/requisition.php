<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/global.php";

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
        <!-- AdminLTE CSS for Popup -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    </head>
    <body class="p-3 bg-light">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">PO LIST - REQ_ID: <?php echo h($reqId); ?> / ITEM_ID: <?php echo h($itemId); ?></h3>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-bordered table-striped table-sm text-sm m-0">
                    <thead class="thead-dark">
                        <tr>
                            <th class="text-center" style="width:50px;">No</th>
                            <th style="width:100px;">PO Date</th>
                            <th style="width:120px;">PO #</th>
                            <th class="text-right" style="width:90px;">Qty</th>
                            <th style="width:90px;">Sup.Code</th>
                            <th>Supplier</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; ?>
                        <?php while ($p = sqlsrv_fetch_array($stmtPO, SQLSRV_FETCH_ASSOC)) { ?>
                            <tr>
                                <td class="text-center"><?php echo h($no); ?></td>
                                <td><?php echo h(fmt_date_view($p["PO DATE"])); ?></td>
                                <td><?php echo h($p["PO #"]); ?></td>
                                <td class="text-right"><?php echo h(number_format(floatval($p["Qty"]), 2, ".", ",")); ?></td>
                                <td><?php echo h($p["Sup.Code"]); ?></td>
                                <td><?php echo h($p["Supplier"]); ?></td>
                            </tr>
                            <?php $no++; ?>
                        <?php } ?>

                        <?php if ($no == 1) { ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">PO belum ada untuk item ini.</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.close()">CLOSE</button>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$message = "";
$error = "";

$action = postv("action", "");
$editId = intval(getv("edit", "0"));
$q = getv("q", "");

// ==== PENANDA AJAX UNTUK SAVE BY ENTER ====
$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1') || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');
// ==========================================

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

                $paramsH = array($reqNo, $reqDate, $reqDue, $reqRem, $reqIsamr, $reqClose, $depId, $reqId);
                $stmtH = sqlsrv_query($conn, $sqlH, $paramsH);
                if ($stmtH === false) $ok = false;
            } else {
                $sqlH = "
                    INSERT INTO dbo.REQUISITION
                    (REQ_NO, REQ_DATE, REQ_DUE, REQ_REM, REQ_ISAMR, REQ_CLOSE, DEP_ID)
                    OUTPUT INSERTED.REQ_ID
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ";

                $paramsH = array($reqNo, $reqDate, $reqDue, $reqRem, $reqIsamr, $reqClose, $depId);
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

                    if ($itemId <= 0 || $qty <= 0) continue;

                    $sqlD = "INSERT INTO dbo.REQ_DETAIL (ITEM_ID, REQ_ID, REQD_QTY, REQD_REM) VALUES (?, ?, ?, ?)";
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
                
                // ==== JIKA DISIMPAN LEWAT ENTER (AJAX) ====
                if ($isAjax) {
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(array("status" => "success", "req_id" => $reqId));
                    exit;
                }
                // ==========================================

                header("Location: requisition.php?edit=" . $reqId . "&msg=saved");
                exit;
            } else {
                sqlsrv_rollback($conn);
                $error = "Simpan gagal:\n" . sql_error_text();
                
                // ==== JIKA ERROR LEWAT ENTER (AJAX) ====
                if ($isAjax) {
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(array("status" => "error", "message" => $error));
                    exit;
                }
                // =======================================
            }
        }
    }
    
    // ==== TANGKAP ERROR VALIDASI FORM JIKA VIA AJAX ====
    if ($error != "" && $isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(array("status" => "error", "message" => $error));
        exit;
    }
    // ===================================================
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
        SELECT REQ_ID, REQ_NO, REQ_DATE, REQ_DUE, REQ_REM, REQ_ISAMR, REQ_CLOSE, DEP_ID
        FROM dbo.REQUISITION WHERE REQ_ID = ?
    ";

    $stmtH = sqlsrv_query($conn, $sqlH, array($editId));

    if ($stmtH !== false) {
        $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
        if ($rh) {
            foreach ($header as $k => $v) {
                if (isset($rh[$k])) $header[$k] = $rh[$k];
            }
            $header["REQ_DATE"] = fmt_date($header["REQ_DATE"]);
            $header["REQ_DUE"] = fmt_date($header["REQ_DUE"]);
        }
    }

    $sqlD = "
        SELECT D.ITEM_ID, D.REQ_ID, D.REQD_QTY, D.REQD_REM, I.ITEM_CODE, I.ITEM_NAME, I.ITEM_UNIT
        FROM dbo.REQ_DETAIL D
        LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID
        WHERE D.REQ_ID = ? ORDER BY I.ITEM_CODE
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
if (getv("msg", "") == "saved") $message = "Requisition berhasil disimpan.";
if (getv("msg", "") == "deleted") $message = "Requisition berhasil dihapus.";

/* ======================================================
   DEPT LIST
====================================================== */
$deptList = array();
$sqlDept = "SELECT DEP_ID, DEP_NAME, DEP_CODE FROM dbo.DEPT ORDER BY DEP_CODE";
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
    SELECT TOP 3000 ITEM_ID, ITEM_CODE, ITEM_NAME, ITEM_UNIT
    FROM dbo.ITEMS
    WHERE ISNULL(ITEM_CODE, '') <> '' AND ISNULL(ITEM_INACTIVE, 0) = 0
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
    SELECT TOP 500 R.REQ_ID, R.REQ_NO, R.REQ_DATE, R.REQ_REM, D.DEP_CODE, D.DEP_NAME
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

if (count($details) == 0) {
    for ($i = 0; $i < 10; $i++) {
        $details[] = array("ITEM_ID" => "", "ITEM_CODE" => "", "ITEM_NAME" => "", "ITEM_UNIT" => "", "REQD_QTY" => "", "REQD_REM" => "");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Requisition</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        /* CSS KHUSUS TAMPILAN WINDOWS CLASSIC / DELPHI */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
        }
        
        .main-container {
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #ece9d8;
        }

        .title-bar {
            background-color: #ffffff;
            text-align: center;
            font-size: 24px;
            font-family: Arial, sans-serif;
            padding: 5px 0;
            color: #000;
        }

        /* HEADER OLIVE */
        .olive-header {
            background-color: #808000; /* Warna Olive Sesuai Gambar */
            padding: 10px;
            color: #000000;
        }

        .lbl-black {
            color: #000000;
            font-size: 11px;
            margin-bottom: 2px;
            display: block;
        }

        .lbl-white {
            color: #ffffff;
            font-size: 11px;
            margin-bottom: 2px;
            display: block;
            font-weight: bold;
        }

        .input-classic {
            width: 100%;
            height: 20px;
            font-size: 12px;
            padding: 0 4px;
            border: 1px solid #7f9db9;
            box-sizing: border-box;
            background: #fff;
            transition: background-color 0.3s; /* Transisi untuk warna AJAX */
        }

        .input-classic:read-only { background-color: #d4d0c8; color: #888; }
        
        select.input-classic { padding: 0; }
        input[type="checkbox"].classic-cb { margin: 2px 0 0 0; }

        .btn-classic {
            background: #f0f0f0;
            border: 1px solid #888;
            border-bottom-color: #444;
            border-right-color: #444;
            padding: 2px 10px;
            font-size: 12px;
            color: #000;
            cursor: pointer;
            box-shadow: inset 1px 1px 0px #fff;
        }
        .btn-classic:active {
            box-shadow: inset 1px 1px 2px #888;
            border: 1px solid #444;
            border-bottom-color: #888;
            border-right-color: #888;
        }
        .btn-action-wide { width: 75px; margin-right: 2px; }

        .fieldset-control {
            border: 1px solid #fff;
            padding: 6px;
            margin-top: 5px;
            position: relative;
        }
        
        .fieldset-control legend {
            color: #fff;
            font-size: 11px;
            background: #808000; /* menyesuaikan dgn backgroud header */
            padding: 0 4px;
            position: absolute;
            top: -8px;
            left: 5px;
        }

        /* GRID TABLE CLASSIC */
        .datagrid-wrapper {
            flex-grow: 1;
            overflow: auto;
            background: #fff;
            border-top: 2px solid #888;
            border-bottom: 2px solid #fff;
        }

        table.datagrid {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        table.datagrid th {
            background-color: #ece9d8;
            border-right: 1px solid #c0c0c0;
            border-bottom: 1px solid #c0c0c0;
            font-weight: normal;
            font-size: 11px;
            padding: 2px 4px;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        table.datagrid td {
            border-right: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            padding: 0;
            font-size: 11px;
        }

        table.datagrid input {
            width: 100%;
            height: 18px;
            border: none;
            padding: 0 4px;
            font-size: 11px;
            background: transparent;
            box-sizing: border-box;
            transition: background-color 0.3s; /* Transisi untuk warna AJAX */
        }
        table.datagrid input:focus {
            background: #e7f1ff;
            outline: 1px solid #000;
        }
        table.datagrid tr.detail-selected td {
            background-color: #d0d0d0;
        }

        .footer-bar {
            background: #ece9d8;
            padding: 5px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #c0c0c0;
        }

        /* AUTOCOMPLETE STYLING */
        .ac-box {
            position: absolute;
            z-index: 9999;
            background: #ffffff;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 2px;
            max-height: 200px;
            overflow-y: auto;
            min-width: 300px;
            display: none;
            font-size: 12px;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
        }
        .ac-item { padding: 4px 8px; cursor: pointer; border-bottom: 1px solid #f4f4f4; }
        .ac-item:hover, .ac-item.active { background: #808000; color: #ffffff; }

        /* Bootstrap grid system utilitas (minimalis) */
        .row { display: flex; flex-wrap: wrap; margin-right: -5px; margin-left: -5px; }
        .col-1 { flex: 0 0 8.333%; max-width: 8.333%; padding: 0 5px; }
        .col-2 { flex: 0 0 16.666%; max-width: 16.666%; padding: 0 5px; }
        .col-3 { flex: 0 0 25%; max-width: 25%; padding: 0 5px; }
        .col-4 { flex: 0 0 33.333%; max-width: 33.333%; padding: 0 5px; }
        .col-5 { flex: 0 0 41.666%; max-width: 41.666%; padding: 0 5px; }
        .col-6 { flex: 0 0 50%; max-width: 50%; padding: 0 5px; }
        .col-8 { flex: 0 0 66.666%; max-width: 66.666%; padding: 0 5px; }
        .col-9 { flex: 0 0 75%; max-width: 75%; padding: 0 5px; }
        .col-12 { flex: 0 0 100%; max-width: 100%; padding: 0 5px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>

    <script>
        // Gunakan pengaman tambahan ?: '[]' agar jika json_encode gagal di PHP 5.4, JS tidak akan syntax error
        var itemData = <?php echo json_encode($itemAuto) ? json_encode($itemAuto) : '[]'; ?>;
        var reqData = <?php echo json_encode($reqAuto) ? json_encode($reqAuto) : '[]'; ?>;
        var jsDeptList = <?php echo json_encode($deptList) ? json_encode($deptList) : '[]'; ?>;

        var acBox = null;
        var acItems = [];
        var acIndex = -1;
        var acMode = "";
        var acRow = -1;
        var rowSeq = <?php echo count($details); ?>;
        var selectedDetailRow = null;

        function byId(id) { return document.getElementById(id); }

        function setValue(id, value) {
            var el = byId(id);
            if (el) el.value = value == null ? "" : value;
        }

        // Sinkronisasi Dept Name berdasarkan dropdown
        function updateDeptName() {
            var sel = byId("dep_id");
            var nameField = byId("dep_name_view");
            var val = sel.value;
            nameField.value = "";
            for(var i=0; i<jsDeptList.length; i++){
                if(jsDeptList[i].DEP_ID == val) {
                    nameField.value = jsDeptList[i].DEP_NAME;
                    break;
                }
            }
        }

        function initAC() { acBox = document.getElementById("acBox"); }
        
        function hideAC() {
            if (acBox) {
                acBox.style.display = "none";
                acBox.innerHTML = "";
            }
            acItems = []; acIndex = -1; acMode = ""; acRow = -1;
        }

        function positionAC(input) {
            initAC();
            var rect = input.getBoundingClientRect();
            acBox.style.left = (rect.left + (window.scrollX || window.pageXOffset)) + "px";
            acBox.style.top = (rect.bottom + (window.scrollY || window.pageYOffset)) + "px";
            acBox.style.width = rect.width < 300 ? "300px" : rect.width + "px";
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
            if (form) form.submit();
        }

        // ==== FUNGSI SIMPAN AJAX (MENGGUNAKAN XMLHTTPREQUEST) ====
        function triggerAjaxSave(inputEl) {
            var form = byId("reqForm");
            
            // Cek kompabilitas browser lama
            if(typeof FormData === "undefined" || typeof XMLHttpRequest === "undefined") {
                submitSave(false); // Fallback ke save reguler jika browser super tua
                return;
            }

            var formData = new FormData(form);
            formData.append("is_ajax", "1"); // Penanda AJAX
            
            var oldBg = inputEl.style.backgroundColor;
            inputEl.style.backgroundColor = "#fff9c4"; // Warna kuning indikator proses
            
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "requisition.php?ajax=1", true);
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            if (data.status === "success") {
                                inputEl.style.backgroundColor = "#c8e6c9"; // Warna hijau sukses
                                setTimeout(function(){ inputEl.style.backgroundColor = oldBg; }, 1000);
                                
                                var currId = byId("req_id").value;
                                if (currId === "" || currId === "0") {
                                    byId("req_id").value = data.req_id;
                                    if (window.history && window.history.replaceState) {
                                        window.history.replaceState(null, null, "?edit=" + data.req_id);
                                    }
                                }
                            } else {
                                alert("Gagal Simpan:\n" + data.message);
                                inputEl.style.backgroundColor = "#ffcdd2"; // Warna merah error
                            }
                        } catch (e) {
                            alert("Gagal membaca respon server.");
                            inputEl.style.backgroundColor = "#ffcdd2";
                        }
                    } else {
                        alert("Error saat menghubungi server.");
                        inputEl.style.backgroundColor = "#ffcdd2";
                    }
                }
            };
            xhr.send(formData);
        }
        // =============================================

        // ==========================================================
        // FUNGSI BANTUAN UNTUK MENCARI BARIS BARU / PINDAH FOKUS
        // ==========================================================
        function focusNextRow(input) {
            var tbody = byId("detailBody");
            if (!tbody) return;
            var rows = tbody.getElementsByTagName("tr");
            var currentRowIndex = -1;
            
            // Cari elemen TR yang mengelilingi input ini
            var el = input;
            while(el && el.nodeName !== "TR") {
                el = el.parentNode;
            }
            
            // Cari indeks baris saat ini
            if (el) {
                for(var i = 0; i < rows.length; i++) {
                    if(rows[i] === el) {
                        currentRowIndex = i;
                        break;
                    }
                }
            }

            if (currentRowIndex === -1) return;

            // Jika ini baris terakhir, buat baris baru
            if (currentRowIndex === rows.length - 1) {
                addRow();
            } else {
                // Jika bukan baris terakhir, pindah fokus ke baris bawahnya
                var nextRow = rows[currentRowIndex + 1];
                var inputs = nextRow.getElementsByTagName("input");
                for(var j=0; j < inputs.length; j++){
                    if(inputs[j].name === "item_code[]") {
                        inputs[j].focus();
                        inputs[j].select();
                        break;
                    }
                }
            }
        }

        function headerKey(e, inputEl) {
            if (e.key === "Enter") {
                if (acMode !== "" && acItems.length > 0) return true;
                e.preventDefault(); 
                
                // Gunakan element 'this' jika ada untuk efek warna, jika tidak arahkan ke submit biasa
                if(inputEl) {
                    triggerAjaxSave(inputEl);
                } else {
                    submitSave(false); 
                }
                return false;
            }
        }

        // ==== ENTER DI QTY & REMARK = OTOMATIS NEW ROW ====
        function qtyKey(e, input) {
            if (e.key === "Enter") {
                e.preventDefault();
                // 1. Simpan Data Current Row ke Server via Ajax
                if (input && input.value != "") {
                    triggerAjaxSave(input);
                }
                
                // 2. Cek baris, pindah fokus atau tambah baris
                focusNextRow(input);
                
                return false;
            }
            if (e.key === "ArrowDown") {
                e.preventDefault();
                var qtys = document.getElementsByClassName("qty-input");
                for (var i = 0; i < qtys.length; i++) {
                    if (qtys[i] === input && qtys[i + 1]) {
                        qtys[i + 1].focus(); qtys[i + 1].select(); break;
                    }
                }
            }
            if (e.key === "ArrowUp") {
                e.preventDefault();
                var qtys2 = document.getElementsByClassName("qty-input");
                for (var j = 0; j < qtys2.length; j++) {
                    if (qtys2[j] === input && qtys2[j - 1]) {
                        qtys2[j - 1].focus(); qtys2[j - 1].select(); break;
                    }
                }
            }
        }

        function remarkKey(e, input) {
            if (e.key === "Enter") {
                e.preventDefault();
                // 1. Simpan Data Current Row ke Server
                triggerAjaxSave(input);
                
                // 2. Pindah fokus atau tambah baris
                focusNextRow(input);
                
                return false;
            }
        }
        // ==========================================================

        function newData() { window.location.href = "requisition.php"; }
        function goEdit(id) { if(!id) return; window.location.href = "requisition.php?edit=" + encodeURIComponent(id); }
        
        function confirmDelete() { return confirm("Yakin hapus requisition ini?"); }

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

        function selectDetailRow(tr){
            var rows=document.querySelectorAll("#detailBody tr");
            for(var i=0;i<rows.length;i++) rows[i].className = rows[i].className.replace(" detail-selected", "");
            selectedDetailRow = tr;
            if(tr) tr.className += " detail-selected";
        }

        function findItemByCode(code) {
            code = (code || "").toUpperCase();
            for (var i = 0; i < itemData.length; i++) {
                if ((itemData[i].ITEM_CODE || "").toUpperCase() == code) return itemData[i];
            }
            return null;
        }

        function findItemByName(name) {
            name = (name || "").toUpperCase();
            for (var i = 0; i < itemData.length; i++) {
                if ((itemData[i].ITEM_NAME || "").toUpperCase() == name) return itemData[i];
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
            acMode = "item"; acRow = row; acItems = []; acIndex = -1;

            if (key.length < 1) { hideAC(); return; }

            for (var i = 0; i < itemData.length; i++) {
                var item = itemData[i];
                var code = (item.ITEM_CODE || "").toUpperCase();
                var name = (item.ITEM_NAME || "").toUpperCase();
                if (code.indexOf(key) >= 0 || name.indexOf(key) >= 0) acItems.push(item);
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
                if (acMode !== "item" || acItems.length == 0) showItemAC(input, row);
                acMove(1, function (item) { return "<b>" + item.ITEM_CODE + "</b> - " + item.ITEM_NAME; }, pickItem);
                return false;
            }
            if (e.key === "ArrowUp") {
                e.preventDefault();
                if (acMode !== "item" || acItems.length == 0) showItemAC(input, row);
                acMove(-1, function (item) { return "<b>" + item.ITEM_CODE + "</b> - " + item.ITEM_NAME; }, pickItem);
                return false;
            }
            if (e.key === "Enter") {
                if (acMode === "item" && acItems.length > 0) {
                    e.preventDefault(); acEnter(pickItem); return false;
                }
                e.preventDefault();
                var item = input.id.indexOf("item_code_") === 0 ? findItemByCode(input.value) : findItemByName(input.value);
                if (item) {
                    setRowItem(row, item);
                    var qty = byId("reqd_qty_" + row);
                    if (qty) { qty.focus(); qty.select(); }
                }
                return false;
            }
            if (e.key === "Escape") hideAC();
        }

        function pickItem(item) {
            var row = acRow;
            setRowItem(row, item);
            hideAC();
            var qty = byId("reqd_qty_" + row);
            if (qty) { qty.focus(); qty.select(); }
        }

        function showReqAC(input) {
            initAC();
            var key = (input.value || "").toUpperCase();
            acMode = "req"; acItems = []; acIndex = -1;

            if (key.length < 1) { hideAC(); return; }

            for (var i = 0; i < reqData.length; i++) {
                var r = reqData[i];
                var text = (r.REQ_NO || "") + " " + (r.REQ_DATE || "") + " " + (r.DEP_CODE || "") + " " + (r.DEP_NAME || "") + " " + (r.REQ_REM || "");
                if (text.toUpperCase().indexOf(key) >= 0) acItems.push(r);
                if (acItems.length >= 30) break;
            }

            positionAC(input);
            renderAC(function (r) {
                return "<b>" + r.REQ_NO + "</b> - " + r.REQ_DATE + " - " + r.DEP_CODE + " " + r.DEP_NAME + " - " + r.REQ_REM;
            }, pickReq);
        }

        function reqSearchKey(e, input) {
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (acMode !== "req" || acItems.length == 0) showReqAC(input);
                acMove(1, function (r) { return "<b>" + r.REQ_NO + "</b> - " + r.REQ_DATE + " - " + r.DEP_CODE + " " + r.DEP_NAME + " - " + r.REQ_REM; }, pickReq);
                return false;
            }
            if (e.key === "ArrowUp") {
                e.preventDefault();
                if (acMode !== "req" || acItems.length == 0) showReqAC(input);
                acMove(-1, function (r) { return "<b>" + r.REQ_NO + "</b> - " + r.REQ_DATE + " - " + r.DEP_CODE + " " + r.DEP_NAME + " - " + r.REQ_REM; }, pickReq);
                return false;
            }
            if (e.key === "Enter") {
                if (acMode === "req" && acItems.length > 0) {
                    e.preventDefault(); acEnter(pickReq); return false;
                }
                return true;
            }
            if (e.key === "Escape") hideAC();
        }

        function pickReq(r) {
            hideAC();
            window.location.href = "requisition.php?edit=" + encodeURIComponent(r.REQ_ID);
        }

        function addRow() {
            var tbody = byId("detailBody");
            var row = rowSeq;
            rowSeq++;

            var tr = document.createElement("tr");
            tr.onclick = function(){ selectDetailRow(this); };
            tr.innerHTML =
                '<td class="text-center align-middle row-no" style="width:25px;"></td>' +
                '<td style="width:120px;">' +
                    '<input type="hidden" name="item_id[]" id="item_id_' + row + '">' +
                    '<input type="text" name="item_code[]" id="item_code_' + row + '" autocomplete="off" oninput="showItemAC(this, ' + row + ')" onkeydown="itemKey(event, this, ' + row + ')">' +
                '</td>' +
                '<td><input type="text" name="item_name[]" id="item_name_' + row + '" autocomplete="off" oninput="showItemAC(this, ' + row + ')" onkeydown="itemKey(event, this, ' + row + ')"></td>' +
                '<td style="width:80px;"><input type="text" class="text-right qty-input" name="reqd_qty[]" id="reqd_qty_' + row + '" onkeydown="qtyKey(event, this)"></td>' +
                '<td style="width:80px;"><input type="text" readonly id="item_unit_' + row + '"></td>' +
                '<td style="width:200px;"><input type="text" name="reqd_rem[]" onkeydown="remarkKey(event, this)"></td>' + 
                '<td class="text-center" style="width:60px;">' +
                    '<button type="button" class="btn-classic" style="padding:0 5px; margin-right:2px;" onclick="showPO(this)" title="PO"><i class="fas fa-shopping-cart"></i></button>' +
                    '<button type="button" class="btn-classic" style="padding:0 5px;" onclick="deleteRow(this)" title="Delete">X</button>' +
                '</td>';

            tbody.appendChild(tr);
            renumberRows();
            selectDetailRow(tr);
            byId("item_code_" + row).focus();
        }

        function deleteRow(btn) {
            var tr = btn.parentNode ? btn.parentNode.parentNode : null;
            var reqId = byId("req_id").value;

            if (!confirm("Hapus baris detail ini?")) return;
            if (tr) tr.parentNode.removeChild(tr);
            
            renumberRows();

            if (reqId != "" && reqId != "0") submitSave(true);
        }

        function renumberRows() {
            var rows = byId("detailBody").getElementsByTagName("tr");
            for (var i = 0; i < rows.length; i++) {
                var noCell = rows[i].getElementsByClassName("row-no")[0];
                if (noCell) noCell.innerHTML = (i==0) ? "<i class='fas fa-caret-right'></i>" : "";
            }
        }

        function showPO(btn) {
            var tr = btn.parentNode ? btn.parentNode.parentNode : null;
            var reqId = byId("req_id").value;
            var itemInput = tr ? tr.querySelector('input[name="item_id[]"]') : null;
            var itemId = itemInput ? itemInput.value : "";

            if (reqId == "" || reqId == "0") { alert("Simpan / pilih requisition dulu."); return; }
            if (itemId == "" || itemId == "0") { alert("Pilih item dulu."); return; }

            var url = "requisition.php?action=po_view&req_id=" + encodeURIComponent(reqId) + "&item_id=" + encodeURIComponent(itemId);
            window.open(url, "PO_LIST", "width=800,height=500,scrollbars=yes,resizable=yes");
        }

        function openSelectedPO() {
            var tr = selectedDetailRow;
            if(!tr){
                var rows = byId("detailBody").getElementsByTagName("tr");
                if(rows.length > 0) tr = rows[0];
            }
            if(!tr) { alert("Pilih baris item dulu."); return; }
            
            var btn = tr.querySelector('button[title="PO"]');
            if(btn) showPO(btn);
        }

        // Cross-browser DOMContentLoaded alternative
        function ready(fn) {
            if (document.readyState != 'loading'){
                fn();
            } else if (document.addEventListener) {
                document.addEventListener('DOMContentLoaded', fn);
            } else {
                document.attachEvent('onreadystatechange', function() {
                    if (document.readyState != 'loading') fn();
                });
            }
        }

        ready(function() {
            var rows = document.getElementById("detailBody").getElementsByTagName("tr");
            for(var i=0; i<rows.length; i++){
                rows[i].onclick = function(){ selectDetailRow(this); };
            }
            renumberRows();
            updateDeptName(); // Initialize Dept Name on load
        });

        if(document.addEventListener) {
            document.addEventListener("click", function (e) {
                initAC();
                if (acBox && !acBox.contains(e.target)) {
                    if (!e.target || !e.target.getAttribute || e.target.getAttribute("autocomplete") !== "off") hideAC();
                }
            });
        } else { // IE8/Classic Fallback
            document.attachEvent("onclick", function (e) {
                var target = e.srcElement || e.target;
                initAC();
                if (acBox && !acBox.contains(target)) {
                    if (!target || !target.getAttribute || target.getAttribute("autocomplete") !== "off") hideAC();
                }
            });
        }
    </script>
</head>
<body>

<div class="main-container">
    <!-- Title Bar -->
    <div class="title-bar">
        Purchase Requisition
    </div>

    <?php if ($message != "") { echo "<div style='background:#d4edda; color:#155724; padding:5px; text-align:center; font-size:12px;'>$message</div>"; } ?>
    <?php if ($error != "") { echo "<div style='background:#f8d7da; color:#721c24; padding:5px; text-align:center; font-size:12px;'>$error</div>"; } ?>

    <form id="reqForm" method="post" action="requisition.php" style="display: flex; flex-direction: column; flex-grow: 1;">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="req_id" id="req_id" value="<?php echo h($header["REQ_ID"]); ?>">
        <input type="hidden" name="allow_empty_detail" id="allow_empty_detail" value="0">

        <!-- HEADER OLIVE AREA -->
        <div class="olive-header">
            <div class="row">
                <div class="col-8">
                    <!-- Baris Atas -->
                    <div class="row">
                        <div class="col-3">
                            <label class="lbl-black">No. PR</label>
                            <input type="text" class="input-classic" name="req_no" value="<?php echo h($header["REQ_NO"]); ?>" onkeydown="headerKey(event, this)">
                        </div>
                        <div class="col-3">
                            <label class="lbl-black">Dept.</label>
                            <select class="input-classic" name="dep_id" id="dep_id" onchange="updateDeptName()" onkeydown="headerKey(event, this)">
                                <option value="">-- Pilih --</option>
                                <?php foreach ($deptList as $d) { ?>
                                    <option value="<?php echo h($d["DEP_ID"]); ?>" <?php echo intval($header["DEP_ID"]) == intval($d["DEP_ID"]) ? "selected" : ""; ?>>
                                        <?php echo h($d["DEP_CODE"]); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="lbl-black">Dept.Name</label>
                            <input type="text" class="input-classic" id="dep_name_view" readonly>
                        </div>
                        <div class="col-1 text-center">
                            <label class="lbl-black">AMR</label>
                            <input type="checkbox" class="classic-cb" id="req_isamr" name="req_isamr" value="1" <?php echo intval($header["REQ_ISAMR"]) == 1 ? "checked" : ""; ?>>
                        </div>
                    </div>
                    
                    <!-- Baris Bawah -->
                    <div class="row mt-1">
                        <div class="col-3">
                            <label class="lbl-black">Date</label>
                            <input type="date" class="input-classic" name="req_date" value="<?php echo h($header["REQ_DATE"]); ?>" onkeydown="headerKey(event, this)">
                        </div>
                        <div class="col-3">
                            <label class="lbl-black">Due</label>
                            <input type="date" class="input-classic" name="req_due" value="<?php echo h($header["REQ_DUE"]); ?>" onkeydown="headerKey(event, this)">
                        </div>
                        <div class="col-5">
                            <label class="lbl-black">Remark</label>
                            <input type="text" class="input-classic" name="req_rem" value="<?php echo h($header["REQ_REM"]); ?>" onkeydown="headerKey(event, this)">
                        </div>
                        <div class="col-1 text-center">
                            <label class="lbl-black">Closed</label>
                            <input type="checkbox" class="classic-cb" id="req_close" name="req_close" value="1" <?php echo intval($header["REQ_CLOSE"]) == 1 ? "checked" : ""; ?>>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="row mt-2">
                        <div class="col-12 text-center">
                            <fieldset class="fieldset-control" style="display: inline-block; padding: 4px 10px;">
                                <button type="button" class="btn-classic btn-action-wide" onclick="newData()">Tambah</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="goEdit('<?php echo h($editId); ?>')">Rubah</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="deleteCurrent()">Hapus</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="submitSave(false)">Simpan</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="newData()">Batal</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="window.location.href='requisition.php'">Refresh</button>
                            </fieldset>
                        </div>
                    </div>
                </div>

                <!-- Kanan: Search Data -->
                <div class="col-4">
                    <div style="display:flex; flex-direction:column; align-items:flex-end;">
                        <div style="margin-bottom: 20px; width: 100%;">
                            <label class="lbl-white text-right">SEARCH REQ</label>
                            <input type="text" name="q" class="input-classic" style="width:100%;" value="<?php echo h($q); ?>" placeholder="Search REQ No / Dept / Remark" autocomplete="off" oninput="showReqAC(this)" onkeydown="reqSearchKey(event, this)">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRID DATA AREA -->
        <div class="datagrid-wrapper">
            <table class="datagrid" id="detailTable">
                <thead>
                    <tr>
                        <th style="width:25px;" class="text-center"></th>
                        <th style="width:120px;">Code</th>
                        <th>Item</th>
                        <th style="width:80px;" class="text-right">Qty</th>
                        <th style="width:80px;">Unit</th>
                        <th style="width:200px;">Remark</th>
                        <th style="width:60px;" class="text-center">ACT</th>
                    </tr>
                </thead>
                <tbody id="detailBody">
                    <?php for ($i = 0; $i < count($details); $i++) { 
                        $d = $details[$i];
                        $qty = isset($d["REQD_QTY"]) ? floatval($d["REQD_QTY"]) : 0;
                    ?>
                        <tr>
                            <td class="text-center align-middle row-no" style="width:25px;"></td>
                            <td style="width:120px;">
                                <input type="hidden" name="item_id[]" id="item_id_<?php echo h($i); ?>" value="<?php echo h($d["ITEM_ID"]); ?>">
                                <input type="text" name="item_code[]" id="item_code_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_CODE"]) ? $d["ITEM_CODE"] : ""); ?>" autocomplete="off" oninput="showItemAC(this, <?php echo h($i); ?>)" onkeydown="itemKey(event, this, <?php echo h($i); ?>)">
                            </td>
                            <td>
                                <input type="text" name="item_name[]" id="item_name_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_NAME"]) ? $d["ITEM_NAME"] : ""); ?>" autocomplete="off" oninput="showItemAC(this, <?php echo h($i); ?>)" onkeydown="itemKey(event, this, <?php echo h($i); ?>)">
                            </td>
                            <td style="width:80px;">
                                <input type="text" class="text-right qty-input" name="reqd_qty[]" id="reqd_qty_<?php echo h($i); ?>" value="<?php echo h($qty == 0 ? "" : $qty); ?>" onkeydown="qtyKey(event, this)">
                            </td>
                            <td style="width:80px;">
                                <input type="text" readonly id="item_unit_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_UNIT"]) ? $d["ITEM_UNIT"] : ""); ?>">
                            </td>
                            <td style="width:200px;">
                                <input type="text" name="reqd_rem[]" value="<?php echo h(isset($d["REQD_REM"]) ? $d["REQD_REM"] : ""); ?>" onkeydown="remarkKey(event, this)">
                            </td>
                            <td class="text-center" style="width:60px;">
                                <button type="button" class="btn-classic" style="padding:0 5px; margin-right:2px;" onclick="showPO(this)" title="PO"><i class="fas fa-shopping-cart"></i></button>
                                <button type="button" class="btn-classic" style="padding:0 5px;" onclick="deleteRow(this)" title="Delete">X</button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER BAR -->
        <div class="footer-bar">
            <div>
                <button type="button" class="btn-classic" onclick="addRow()"><i class="fas fa-plus"></i> Tambah Baris</button>
            </div>
            <div style="text-align: right;">
                <button type="button" class="btn-classic" onclick="openSelectedPO()" style="width: 80px; height:24px;">PO'S</button>
            </div>
        </div>
    </form>

    <form id="deleteForm" method="post" action="requisition.php" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="req_id" id="delete_req_id" value="">
    </form>
</div>

<div id="acBox" class="ac-box"></div>

</body>
</html>