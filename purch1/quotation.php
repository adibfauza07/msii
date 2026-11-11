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
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    </head>
    <body class="p-3 bg-light">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">QUOTATION HISTORY - ITEM_ID : <?php echo h($itemId); ?></h3>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-bordered table-striped table-sm text-sm m-0">
                    <thead class="thead-dark">
                        <tr>
                            <th class="text-center" style="width:40px;">No</th>
                            <th style="width:100px;">Quo No</th>
                            <th style="width:90px;">Date</th>
                            <th style="width:90px;">Eff Date</th>
                            <th class="text-center" style="width:70px;">Curr</th>
                            <th style="width:90px;">Sup.Code</th>
                            <th>Supplier</th>
                            <th class="text-right" style="width:80px;">Price</th>
                            <th class="text-right" style="width:80px;">Min.Qty</th>
                            <th style="width:70px;">Unit</th>
                            <th style="width:80px;">Term</th>
                            <th class="text-center" style="width:70px;">Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; ?>
                        <?php while ($r = sqlsrv_fetch_array($stmtHis, SQLSRV_FETCH_ASSOC)) { ?>
                            <tr>
                                <td class="text-center"><?php echo h($no); ?></td>
                                <td><?php echo h($r["QUO_NO"]); ?></td>
                                <td><?php echo h(fmt_date_view($r["QUO_DATE"])); ?></td>
                                <td><?php echo h(fmt_date_view($r["QUO_EFFDATE"])); ?></td>
                                <td class="text-center"><?php echo h($r["CURR_CODE"]); ?></td>
                                <td><?php echo h($r["SUP_CODE"]); ?></td>
                                <td><?php echo h($r["SUP_COMP"]); ?></td>
                                <td class="text-right"><?php echo h(number_format(floatval($r["QUOD_PRICE"]), 2, ".", ",")); ?></td>
                                <td class="text-right"><?php echo h(number_format(floatval($r["QUOD_MINQTY"]), 2, ".", ",")); ?></td>
                                <td><?php echo h($r["QUOD_UNIT"]); ?></td>
                                <td><?php echo h($r["QUOD_TERM"]); ?></td>
                                <td class="text-center">
                                    <?php if(intval($r["QUOD_ACTIVE"]) == 1) { echo '<span class="badge badge-success">YES</span>'; } ?>
                                </td>
                            </tr>
                            <?php $no++; ?>
                        <?php } ?>

                        <?php if ($no == 1) { ?>
                            <tr>
                                <td colspan="12" class="text-center text-muted">History quotation tidak ditemukan.</td>
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
   DELETE QUOTATION HEADER
====================================================== */
if ($action == "delete") {
    $quoId = intval(postv("quo_id", "0"));

    if ($quoId <= 0) {
        $error = "Pilih quotation dulu.";
    } else {
        $sqlCek = "SELECT COUNT(*) AS CNT FROM dbo.QUOT_DETAIL WHERE QUO_ID = ?";
        $stmtCek = sqlsrv_query($conn, $sqlCek, array($quoId));

        if ($stmtCek === false) {
            $error = "Cek detail quotation gagal:\n" . sql_error_text();
        } else {
            $rCek = sqlsrv_fetch_array($stmtCek, SQLSRV_FETCH_ASSOC);
            $detailCount = intval($rCek["CNT"]);

            if ($detailCount > 0) {
                $error = "Tidak dapat menghapus, masih ada detail quotation.";
            } else {
                $sqlDel = "DELETE FROM dbo.QUOTATION WHERE QUO_ID = ?";
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

    if ($quoDate == "") $quoDate = date("Y-m-d");
    if ($quoEffDate == "") $quoEffDate = $quoDate;
    if ($quoNo == "") $quoNo = generate_quo_no($conn, $quoDate);

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
            if ($itemId <= 0 && $itemCode != "") $itemId = find_item_id_by_code($conn, $itemCode);
            $price = isset($prices[$i]) ? to_float($prices[$i]) : 0;
            if ($itemId > 0 && $price > 0) { $hasDetail = true; break; }
        }

        if (!$hasDetail && !($allowEmpty == 1 && $quoId > 0)) {
            $error = "Detail quotation minimal 1 baris dan price harus lebih dari 0.";
        } else {
            sqlsrv_begin_transaction($conn);
            $ok = true;

            if ($quoId > 0) {
                $sqlH = "UPDATE dbo.QUOTATION SET SUP_ID = ?, QUO_NO = ?, QUO_DATE = ?, CURR_CODE = ?, QUO_EFFDATE = ? WHERE QUO_ID = ?";
                $paramsH = array($supId, $quoNo, $quoDate, $currCode, $quoEffDate, $quoId);
                $stmtH = sqlsrv_query($conn, $sqlH, $paramsH);
                if ($stmtH === false) $ok = false;
            } else {
                $sqlH = "INSERT INTO dbo.QUOTATION (SUP_ID, QUO_NO, QUO_DATE, CURR_CODE, QUO_EFFDATE) OUTPUT INSERTED.QUO_ID VALUES (?, ?, ?, ?, ?)";
                $paramsH = array($supId, $quoNo, $quoDate, $currCode, $quoEffDate);
                $stmtH = sqlsrv_query($conn, $sqlH, $paramsH);
                if ($stmtH === false) {
                    $ok = false;
                } else {
                    $newRow = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_NUMERIC);
                    if ($newRow) $quoId = intval($newRow[0]); else $ok = false;
                }
            }

            if ($ok) {
                $stmtDel = sqlsrv_query($conn, "DELETE FROM dbo.QUOT_DETAIL WHERE QUO_ID = ?", array($quoId));
                if ($stmtDel === false) $ok = false;
            }

            if ($ok) {
                for ($i = 0; $i < count($itemIds); $i++) {
                    $itemId = intval($itemIds[$i]);
                    $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";
                    if ($itemId <= 0 && $itemCode != "") $itemId = find_item_id_by_code($conn, $itemCode);

                    $price  = isset($prices[$i]) ? to_float($prices[$i]) : 0;
                    $minQty = isset($minQtys[$i]) ? to_float($minQtys[$i]) : 0;
                    $unit   = isset($units[$i]) ? trim((string)$units[$i]) : "";
                    $term   = isset($terms[$i]) ? trim((string)$terms[$i]) : "";
                    
                    // Note: Checkbox logic in HTML passes value if checked, otherwise missing
                    $active = isset($actives[$i]) ? 1 : 0;

                    if ($itemId <= 0 || $price <= 0) continue;

                    $sqlD = "INSERT INTO dbo.QUOT_DETAIL (QUOD_PRICE, QUOD_MINQTY, QUOD_UNIT, QUOD_TERM, ITEM_ID, QUO_ID, QUOD_ACTIVE) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $paramsD = array($price, $minQty, $unit, $term, $itemId, $quoId, $active);
                    $stmtD = sqlsrv_query($conn, $sqlD, $paramsD);

                    if ($stmtD === false) { $ok = false; break; }
                }
            }

            if ($ok) {
                sqlsrv_commit($conn);
                
                // ==== JIKA DISIMPAN LEWAT ENTER (AJAX) ====
                if ($isAjax) {
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(array("status" => "success", "quo_id" => $quoId));
                    exit;
                }
                // ==========================================

                header("Location: quotation.php?edit=" . $quoId . "&msg=saved");
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
$header = array("QUO_ID" => "", "SUP_ID" => "", "SUP_CODE" => "", "SUP_COMP" => "", "QUO_NO" => "", "QUO_DATE" => date("Y-m-d"), "CURR_CODE" => "IDR", "QUO_EFFDATE" => date("Y-m-d"));
$details = array();

if ($editId > 0) {
    $sqlH = "SELECT Q.QUO_ID, Q.SUP_ID, Q.QUO_NO, Q.QUO_DATE, Q.CURR_CODE, Q.QUO_EFFDATE, S.SUP_CODE, S.SUP_COMP FROM dbo.QUOTATION Q LEFT JOIN dbo.SUPPLIER S ON Q.SUP_ID = S.SUP_ID WHERE Q.QUO_ID = ?";
    $stmtH = sqlsrv_query($conn, $sqlH, array($editId));
    if ($stmtH !== false) {
        $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
        if ($rh) {
            foreach ($header as $k => $v) if (isset($rh[$k])) $header[$k] = $rh[$k];
            $header["QUO_DATE"] = fmt_date($header["QUO_DATE"]);
            $header["QUO_EFFDATE"] = fmt_date($header["QUO_EFFDATE"]);
        }
    }

    $sqlD = "SELECT D.QUOD_PRICE, D.QUOD_MINQTY, D.QUOD_UNIT, D.QUOD_TERM, D.ITEM_ID, D.QUO_ID, D.QUOD_ACTIVE, I.ITEM_CODE, I.ITEM_NAME, I.ITEM_UNIT FROM dbo.QUOT_DETAIL D LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID WHERE D.QUO_ID = ? ORDER BY I.ITEM_CODE";
    $stmtD = sqlsrv_query($conn, $sqlD, array($editId));
    if ($stmtD !== false) {
        while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) $details[] = $rd;
    }
}

/* ======================================================
   MESSAGE
====================================================== */
if (getv("msg", "") == "saved") $message = "Quotation berhasil disimpan.";
if (getv("msg", "") == "deleted") $message = "Quotation berhasil dihapus.";

/* ======================================================
   SUPPLIER AUTO COMPLETE
====================================================== */
$supplierAuto = array();
$sqlSup = "SELECT TOP 1000 SUP_ID, SUP_CODE, SUP_COMP, CURR_CODE FROM dbo.SUPPLIER WHERE ISNULL(SUP_CODE, '') <> '' ORDER BY SUP_CODE";
$stmtSup = sqlsrv_query($conn, $sqlSup);
if ($stmtSup !== false) {
    while ($s = sqlsrv_fetch_array($stmtSup, SQLSRV_FETCH_ASSOC)) {
        $supplierAuto[] = array("SUP_ID" => intval($s["SUP_ID"]), "SUP_CODE" => trim((string)$s["SUP_CODE"]), "SUP_COMP" => trim((string)$s["SUP_COMP"]), "CURR_CODE" => trim((string)$s["CURR_CODE"]));
    }
}

/* ======================================================
   ITEM AUTO COMPLETE
====================================================== */
$itemAuto = array();
$sqlItem = "SELECT TOP 3000 ITEM_ID, ITEM_CODE, ITEM_NAME, ITEM_UNIT, ITEM_COST FROM dbo.ITEMS WHERE ISNULL(ITEM_CODE, '') <> '' AND ISNULL(ITEM_INACTIVE, 0) = 0 ORDER BY ITEM_CODE";
$stmtItem = sqlsrv_query($conn, $sqlItem);
if ($stmtItem !== false) {
    while ($it = sqlsrv_fetch_array($stmtItem, SQLSRV_FETCH_ASSOC)) {
        $itemAuto[] = array("ITEM_ID" => intval($it["ITEM_ID"]), "ITEM_CODE" => trim((string)$it["ITEM_CODE"]), "ITEM_NAME" => trim((string)$it["ITEM_NAME"]), "ITEM_UNIT" => trim((string)$it["ITEM_UNIT"]), "ITEM_COST" => isset($it["ITEM_COST"]) ? floatval($it["ITEM_COST"]) : 0);
    }
}

/* ======================================================
   QUOTATION AUTO COMPLETE
====================================================== */
$quoAuto = array();
$sqlQuoAuto = "SELECT TOP 500 Q.QUO_ID, Q.QUO_NO, Q.QUO_DATE, Q.CURR_CODE, S.SUP_CODE, S.SUP_COMP FROM dbo.QUOTATION Q LEFT JOIN dbo.SUPPLIER S ON Q.SUP_ID = S.SUP_ID ORDER BY Q.QUO_DATE DESC, Q.QUO_NO DESC";
$stmtQuoAuto = sqlsrv_query($conn, $sqlQuoAuto);
if ($stmtQuoAuto !== false) {
    while ($qa = sqlsrv_fetch_array($stmtQuoAuto, SQLSRV_FETCH_ASSOC)) {
        $quoAuto[] = array("QUO_ID" => intval($qa["QUO_ID"]), "QUO_NO" => trim((string)$qa["QUO_NO"]), "QUO_DATE" => fmt_date_view($qa["QUO_DATE"]), "CURR_CODE" => trim((string)$qa["CURR_CODE"]), "SUP_CODE" => trim((string)$qa["SUP_CODE"]), "SUP_COMP" => trim((string)$qa["SUP_COMP"]));
    }
}

/* ======================================================
   CURRENCY LIST
====================================================== */
$currList = array("IDR", "USD", "JPY");
$sqlCurr = "SELECT DISTINCT LTRIM(RTRIM(CURR_CODE)) AS CURR_CODE FROM dbo.CURR WHERE ISNULL(CURR_CODE, '') <> '' ORDER BY LTRIM(RTRIM(CURR_CODE))";
$stmtCurr = @sqlsrv_query($conn, $sqlCurr);
if ($stmtCurr !== false) {
    $currList = array();
    while ($c = sqlsrv_fetch_array($stmtCurr, SQLSRV_FETCH_ASSOC)) {
        $v = trim((string)$c["CURR_CODE"]);
        if ($v != "") $currList[] = $v;
    }
}

/* ======================================================
   LIST QUOTATION (Jika diperlukan)
====================================================== */
$where = "";
$paramsList = array();

if ($q != "") {
    $where = "WHERE Q.QUO_NO LIKE ? OR S.SUP_CODE LIKE ? OR S.SUP_COMP LIKE ?";
    $paramsList[] = "%" . $q . "%";
    $paramsList[] = "%" . $q . "%";
    $paramsList[] = "%" . $q . "%";
}

$sqlList = "SELECT TOP 300 Q.QUO_ID, Q.QUO_NO, Q.QUO_DATE, Q.QUO_EFFDATE, Q.CURR_CODE, S.SUP_CODE, S.SUP_COMP, COUNT(D.ITEM_ID) AS DETAIL_COUNT FROM dbo.QUOTATION Q LEFT JOIN dbo.SUPPLIER S ON Q.SUP_ID = S.SUP_ID LEFT JOIN dbo.QUOT_DETAIL D ON Q.QUO_ID = D.QUO_ID $where GROUP BY Q.QUO_ID, Q.QUO_NO, Q.QUO_DATE, Q.QUO_EFFDATE, Q.CURR_CODE, S.SUP_CODE, S.SUP_COMP ORDER BY Q.QUO_DATE DESC, Q.QUO_NO DESC";
$stmtList = sqlsrv_query($conn, $sqlList, $paramsList);
if ($stmtList === false) die("<pre>Query list quotation error:\n" . sql_error_text() . "</pre>");

if (count($details) == 0) {
    for ($i = 0; $i < 10; $i++) {
        $details[] = array("ITEM_ID" => "", "ITEM_CODE" => "", "ITEM_NAME" => "", "ITEM_UNIT" => "", "QUOD_PRICE" => "", "QUOD_MINQTY" => "", "QUOD_UNIT" => "", "QUOD_TERM" => "", "QUOD_ACTIVE" => 1);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quotation</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        /* CSS KHUSUS TAMPILAN WINDOWS CLASSIC / DELPHI (Menyesuaikan modul PO) */
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
            color: #ffffff;
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
            transition: background-color 0.3s;
        }

        .input-classic:read-only { background-color: #a9a9a9; color: #000; }
        
        select.input-classic { padding: 0; }

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
            display: inline-block;
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

        table.datagrid input[type="text"], table.datagrid input[type="date"] {
            width: 100%;
            height: 18px;
            border: none;
            padding: 0 4px;
            font-size: 11px;
            background: transparent;
            box-sizing: border-box;
            transition: background-color 0.3s;
        }
        table.datagrid input[type="text"]:focus, table.datagrid input[type="date"]:focus {
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
        .col-8 { flex: 0 0 66.666%; max-width: 66.666%; padding: 0 5px; }
        .col-12 { flex: 0 0 100%; max-width: 100%; padding: 0 5px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>

    <script>
        // Gunakan pengaman tambahan ?: '[]' agar jika json_encode gagal di PHP 5.4, JS tidak akan syntax error
        var supplierData = <?php echo json_encode($supplierAuto) ? json_encode($supplierAuto) : '[]'; ?>;
        var itemData = <?php echo json_encode($itemAuto) ? json_encode($itemAuto) : '[]'; ?>;
        var quoData = <?php echo json_encode($quoAuto) ? json_encode($quoAuto) : '[]'; ?>;

        var acBox = null;
        var acItems = [];
        var acIndex = -1;
        var acMode = "";
        var acRow = -1;
        var rowSeq = <?php echo count($details); ?>;
        var selectedDetailRow = null;

        function byId(id) { return document.getElementById(id); }
        function setValue(id, value) { var el = byId(id); if (el) el.value = value == null ? "" : value; }

        function initAC() { acBox = document.getElementById("acBox"); }

        function hideAC() {
            if (acBox) { acBox.style.display = "none"; acBox.innerHTML = ""; }
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

        function submitSave(allowEmpty) {
            if (byId("allow_empty_detail")) {
                byId("allow_empty_detail").value = allowEmpty ? "1" : "0";
            }
            var form = byId("quoForm");
            if (form) form.submit();
        }
        
        // ==== FUNGSI SIMPAN AJAX (MENGGUNAKAN XMLHTTPREQUEST) ====
        function triggerAjaxSave(inputEl) {
            var form = byId("quoForm");
            
            if(typeof FormData === "undefined" || typeof XMLHttpRequest === "undefined") {
                submitSave(false); 
                return;
            }

            var formData = new FormData(form);
            formData.append("is_ajax", "1"); 
            
            var oldBg = inputEl.style.backgroundColor;
            inputEl.style.backgroundColor = "#fff9c4"; 
            
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "quotation.php?ajax=1", true);
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            if (data.status === "success") {
                                inputEl.style.backgroundColor = "#c8e6c9"; 
                                setTimeout(function(){ inputEl.style.backgroundColor = oldBg; }, 1000);
                                
                                var currId = byId("quo_id").value;
                                if (currId === "" || currId === "0") {
                                    byId("quo_id").value = data.quo_id;
                                    if (window.history && window.history.replaceState) {
                                        window.history.replaceState(null, null, "?edit=" + data.quo_id);
                                    }
                                }
                            } else {
                                alert("Gagal Simpan:\n" + data.message);
                                inputEl.style.backgroundColor = "#ffcdd2"; 
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

        // ==========================================================
        // FUNGSI BANTUAN UNTUK MENCARI BARIS BARU / PINDAH FOKUS
        // ==========================================================
        function focusNextRow(input) {
            var tbody = byId("detailBody");
            if (!tbody) return;
            var rows = tbody.getElementsByTagName("tr");
            var currentRowIndex = -1;
            
            var el = input;
            while(el && el.nodeName !== "TR") {
                el = el.parentNode;
            }
            
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

        // Mencegah form tersubmit biasa, lalu simpan ajax dan pindah baris
        function fieldEnterSave(e, inputEl) {
            if (e.key === "Enter") {
                e.preventDefault();
                triggerAjaxSave(inputEl);
                focusNextRow(inputEl);
                return false; 
            }
        }
        // ==========================================================

        function newData() { window.location.href = "quotation.php"; }
        function goEdit(id) { if(!id) return; window.location.href = "quotation.php?edit=" + encodeURIComponent(id); }
        function confirmDelete() { return confirm("Yakin hapus quotation ini?"); }

        function deleteCurrent() {
            var id = byId("quo_id").value;
            if (id == "" || id == "0") { alert("Pilih quotation dulu."); return; }
            if (!confirmDelete()) return;
            byId("delete_quo_id").value = id;
            byId("deleteForm").submit();
        }

        function selectDetailRow(tr){
            var rows = document.querySelectorAll("#detailBody tr");
            for(var i = 0; i < rows.length; i++) rows[i].className = rows[i].className.replace(" detail-selected", "");
            selectedDetailRow = tr;
            if(tr) tr.className += " detail-selected";
        }

        function fillSupplier(s) {
            setValue("sup_id", s.SUP_ID);
            setValue("sup_code", s.SUP_CODE);
            setValue("sup_comp", s.SUP_COMP);
            if (s.CURR_CODE) setValue("curr_code", s.CURR_CODE);
        }

        function showSupplierAC(input) {
            initAC();
            var key = (input.value || "").toUpperCase();
            acMode = "supplier"; acItems = []; acIndex = -1;

            if (key.length < 1) { hideAC(); return; }
            for (var i = 0; i < supplierData.length; i++) {
                var s = supplierData[i];
                var text = (s.SUP_CODE || "") + " " + (s.SUP_COMP || "");
                if (text.toUpperCase().indexOf(key) >= 0) acItems.push(s);
                if (acItems.length >= 30) break;
            }

            positionAC(input);
            renderAC(function (s) { return "<b>" + s.SUP_CODE + "</b> - " + s.SUP_COMP; }, pickSupplier);
        }

        function supplierKey(e, input) {
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (acMode !== "supplier" || acItems.length == 0) showSupplierAC(input);
                acMove(1, function (s) { return "<b>" + s.SUP_CODE + "</b> - " + s.SUP_COMP; }, pickSupplier);
                return false;
            }
            if (e.key === "ArrowUp") {
                e.preventDefault();
                if (acMode !== "supplier" || acItems.length == 0) showSupplierAC(input);
                acMove(-1, function (s) { return "<b>" + s.SUP_CODE + "</b> - " + s.SUP_COMP; }, pickSupplier);
                return false;
            }
            if (e.key === "Enter") {
                if (acMode === "supplier" && acItems.length > 0) {
                    e.preventDefault(); acEnter(pickSupplier); return false;
                }
                e.preventDefault(); submitSave(false); return false;
            }
            if (e.key === "Escape") hideAC();
        }

        function pickSupplier(s) {
            fillSupplier(s); hideAC();
            var quoNo = byId("quo_no");
            if (quoNo) { quoNo.focus(); quoNo.select(); }
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
            renderAC(function (item) { return "<b>" + item.ITEM_CODE + "</b> - " + item.ITEM_NAME; }, pickItem);
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
                if (acMode === "item" && acItems.length > 0) { e.preventDefault(); acEnter(pickItem); return false; }
                e.preventDefault(); return false;
            }
            if (e.key === "Escape") hideAC();
        }

        function pickItem(item) {
            var row = acRow;
            setRowItem(row, item); hideAC();
            var price = byId("quod_price_" + row);
            if (price) { price.focus(); price.select(); }
        }

        function headerKey(e) {
            if (e.key === "Enter") {
                if (acMode !== "" && acItems.length > 0) return true;
                e.preventDefault(); submitSave(false); return false;
            }
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
                '<td style="width:60px;"><input type="text" name="quod_unit[]" id="quod_unit_' + row + '"></td>' +
                '<td style="width:90px;"><input type="text" class="text-right" name="quod_price[]" id="quod_price_' + row + '" onkeydown="fieldEnterSave(event, this)"></td>' +
                '<td style="width:80px;"><input type="text" class="text-right" name="quod_minqty[]" onkeydown="fieldEnterSave(event, this)"></td>' +
                '<td style="width:100px;"><input type="text" name="quod_term[]" onkeydown="fieldEnterSave(event, this)"></td>' +
                '<td class="text-left align-middle" style="width:100px; padding-left:5px !important;">' +
                    '<label style="font-size:11px; margin:0;"><input type="checkbox" name="quod_active[' + row + ']" value="1" checked style="width:auto; height:auto; margin-right:4px;"> Active</label>' +
                '</td>' +
                '<td class="text-center" style="width:40px;">' +
                    '<button type="button" class="btn-classic" style="padding:0 5px;" onclick="deleteRow(this)" title="Delete">X</button>' +
                '</td>';

            tbody.appendChild(tr);
            renumberRows();
            selectDetailRow(tr);
            byId("item_code_" + row).focus();
        }

        function deleteRow(btn) {
            var tr = btn.parentNode ? btn.parentNode.parentNode : null;
            if (!confirm("Hapus baris detail ini?")) return;
            if(tr) tr.parentNode.removeChild(tr);
            renumberRows();
            var quoId = byId("quo_id").value;
            if (quoId != "" && quoId != "0") submitSave(true);
        }

        function renumberRows() {
            var rows = byId("detailBody").getElementsByTagName("tr");
            for (var i = 0; i < rows.length; i++) {
                var noCell = rows[i].getElementsByClassName("row-no")[0];
                if (noCell) noCell.innerHTML = (i==0) ? "<i class='fas fa-caret-right'></i>" : "";
            }
        }

        function showHistory(btn) {
            var tr = btn.parentNode ? btn.parentNode.parentNode : null;
            var itemInput = tr ? tr.querySelector('input[name="item_id[]"]') : null;
            var itemId = itemInput ? itemInput.value : "";
            if (itemId == "" || itemId == "0") { alert("Pilih item dulu."); return; }
            var url = "quotation.php?action=quo_history&item_id=" + encodeURIComponent(itemId);
            window.open(url, "QUO_HISTORY", "width=980,height=450,scrollbars=yes,resizable=yes");
        }

        function openSelectedHistory() {
            var tr = selectedDetailRow;
            if(!tr){
                var rows = byId("detailBody").getElementsByTagName("tr");
                if(rows.length > 0) tr = rows[0];
            }
            if(!tr) { alert("Pilih baris item dulu."); return; }
            
            var itemInput = tr.querySelector('input[name="item_id[]"]');
            var itemId = itemInput ? itemInput.value : "";
            if (itemId == "" || itemId == "0") { alert("Pilih item dulu."); return; }
            
            var url = "quotation.php?action=quo_history&item_id=" + encodeURIComponent(itemId);
            window.open(url, "QUO_HISTORY", "width=980,height=450,scrollbars=yes,resizable=yes");
        }

        function showQuoAC(input) {
            initAC();
            var key = (input.value || "").toUpperCase();
            acMode = "quo"; acItems = []; acIndex = -1;

            if (key.length < 1) { hideAC(); return; }

            for (var i = 0; i < quoData.length; i++) {
                var q = quoData[i];
                var text = (q.QUO_NO || "") + " " + (q.QUO_DATE || "") + " " + (q.SUP_CODE || "") + " " + (q.SUP_COMP || "");
                if (text.toUpperCase().indexOf(key) >= 0) acItems.push(q);
                if (acItems.length >= 30) break;
            }

            positionAC(input);
            renderAC(function (q) {
                return "<b>" + q.QUO_NO + "</b> - " + q.QUO_DATE + "<br><small style='color:#666;'>" + q.SUP_CODE + " " + q.SUP_COMP + "</small>";
            }, pickQuo);
        }

        function quoSearchKey(e, input) {
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (acMode !== "quo" || acItems.length == 0) showQuoAC(input);
                acMove(1, function (q) { return "<b>" + q.QUO_NO + "</b> - " + q.QUO_DATE + "<br><small style='color:#666;'>" + q.SUP_CODE + " " + q.SUP_COMP + "</small>"; }, pickQuo);
                return false;
            }
            if (e.key === "ArrowUp") {
                e.preventDefault();
                if (acMode !== "quo" || acItems.length == 0) showQuoAC(input);
                acMove(-1, function (q) { return "<b>" + q.QUO_NO + "</b> - " + q.QUO_DATE + "<br><small style='color:#666;'>" + q.SUP_CODE + " " + q.SUP_COMP + "</small>"; }, pickQuo);
                return false;
            }
            if (e.key === "Enter") {
                if (acMode === "quo" && acItems.length > 0) { e.preventDefault(); acEnter(pickQuo); return false; }
                return true;
            }
            if (e.key === "Escape") hideAC();
        }

        function pickQuo(q) {
            hideAC();
            window.location.href = "quotation.php?edit=" + encodeURIComponent(q.QUO_ID);
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
        });

        if(document.addEventListener) {
            document.addEventListener("click", function (e) {
                initAC();
                if (acBox && !acBox.contains(e.target)) {
                    if (!e.target || !e.target.getAttribute || e.target.getAttribute("autocomplete") !== "off") hideAC();
                }
            });
        } else {
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
        Quotation
    </div>

    <?php if ($message != "") { echo "<div style='background:#d4edda; color:#155724; padding:5px; text-align:center; font-size:12px;'>$message</div>"; } ?>
    <?php if ($error != "") { echo "<div style='background:#f8d7da; color:#721c24; padding:5px; text-align:center; font-size:12px;'>$error</div>"; } ?>

    <form id="quoForm" method="post" action="quotation.php" style="display: flex; flex-direction: column; flex-grow: 1;">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="quo_id" id="quo_id" value="<?php echo h($header["QUO_ID"]); ?>">
        <input type="hidden" name="allow_empty_detail" id="allow_empty_detail" value="0">

        <!-- HEADER OLIVE AREA -->
        <div class="olive-header">
            <div class="row">
                <div class="col-8">
                    <!-- Baris Atas -->
                    <div class="row">
                        <div class="col-2">
                            <label class="lbl-white">Supplier</label>
                            <input type="hidden" name="sup_id" id="sup_id" value="<?php echo h($header["SUP_ID"]); ?>">
                            <input type="text" class="input-classic" name="sup_code" id="sup_code" value="<?php echo h($header["SUP_CODE"]); ?>" autocomplete="off" oninput="showSupplierAC(this)" onkeydown="supplierKey(event, this)">
                        </div>
                        <div class="col-8">
                            <label class="lbl-white">Company</label>
                            <input type="text" class="input-classic" name="sup_comp" id="sup_comp" value="<?php echo h($header["SUP_COMP"]); ?>" readonly>
                        </div>
                    </div>
                    
                    <!-- Baris Bawah -->
                    <div class="row mt-1">
                        <div class="col-2">
                            <label class="lbl-white">No.</label>
                            <input type="text" class="input-classic" name="quo_no" id="quo_no" value="<?php echo h($header["QUO_NO"]); ?>" onkeydown="headerKey(event, this)">
                        </div>
                        <div class="col-2">
                            <label class="lbl-white">Curr.</label>
                            <select class="input-classic" name="curr_code" id="curr_code" onkeydown="headerKey(event, this)">
                                <?php foreach ($currList as $c) { ?>
                                    <option value="<?php echo h($c); ?>" <?php echo trim((string)$header["CURR_CODE"]) == $c ? "selected" : ""; ?>><?php echo h($c); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="lbl-white">Effective Date</label>
                            <input type="date" class="input-classic" name="quo_date" value="<?php echo h($header["QUO_DATE"]); ?>" onkeydown="headerKey(event, this)">
                        </div>
                        <div class="col-3">
                            <label class="lbl-white">End Date</label>
                            <input type="date" class="input-classic" name="quo_effdate" value="<?php echo h($header["QUO_EFFDATE"]); ?>" onkeydown="headerKey(event, this)">
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="row mt-2">
                        <div class="col-12 text-center">
                            <fieldset class="fieldset-control" style="border:none; padding: 4px 10px;">
                                <button type="button" class="btn-classic btn-action-wide" onclick="newData()">Tambah</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="goEdit('<?php echo h($editId); ?>')">Rubah</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="deleteCurrent()">Hapus</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="submitSave(false)">Simpan</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="newData()">Batal</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="window.location.href='quotation.php'">Refresh</button>
                            </fieldset>
                        </div>
                    </div>
                </div>

                <!-- Kanan: Search Data -->
                <div class="col-4">
                    <div style="display:flex; flex-direction:column; align-items:flex-end;">
                        <div style="margin-bottom: 20px; width: 100%;">
                            <label class="lbl-white text-right" style="font-weight:bold;">SEARCH QUOTATION</label>
                            <input type="text" name="q" class="input-classic" style="width:100%;" value="<?php echo h($q); ?>" placeholder="Search Quo No / Supplier" autocomplete="off" oninput="showQuoAC(this)" onkeydown="quoSearchKey(event, this)">
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
                        <th>Item Name</th>
                        <th style="width:60px;">Unit</th>
                        <th style="width:90px;" class="text-right">Price</th>
                        <th style="width:80px;" class="text-right">Min.Qty</th>
                        <th style="width:100px;">Term</th>
                        <th style="width:100px;">QUOD_ACTIVE</th>
                        <th style="width:40px;" class="text-center">ACT</th>
                    </tr>
                </thead>
                <tbody id="detailBody">
                    <?php for ($i = 0; $i < count($details); $i++) { 
                        $d = $details[$i];
                        $price = isset($d["QUOD_PRICE"]) ? floatval($d["QUOD_PRICE"]) : 0;
                        $minqty = isset($d["QUOD_MINQTY"]) ? floatval($d["QUOD_MINQTY"]) : 0;
                        $active = isset($d["QUOD_ACTIVE"]) ? intval($d["QUOD_ACTIVE"]) : 0;
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
                            <td style="width:60px;">
                                <input type="text" name="quod_unit[]" id="quod_unit_<?php echo h($i); ?>" value="<?php echo h(isset($d["QUOD_UNIT"]) ? $d["QUOD_UNIT"] : ""); ?>">
                            </td>
                            
                            <!-- Menerapkan fieldEnterSave (untuk ajax save dan next row) pada input ini -->
                            <td style="width:90px;">
                                <input type="text" class="text-right" name="quod_price[]" id="quod_price_<?php echo h($i); ?>" value="<?php echo h($price == 0 ? "" : $price); ?>" onkeydown="fieldEnterSave(event, this)">
                            </td>
                            <td style="width:80px;">
                                <input type="text" class="text-right" name="quod_minqty[]" value="<?php echo h($minqty == 0 ? "" : $minqty); ?>" onkeydown="fieldEnterSave(event, this)">
                            </td>
                            <td style="width:100px;">
                                <input type="text" name="quod_term[]" value="<?php echo h(isset($d["QUOD_TERM"]) ? $d["QUOD_TERM"] : ""); ?>" onkeydown="fieldEnterSave(event, this)">
                            </td>
                            
                            <td class="text-left align-middle" style="width:100px; padding-left:5px !important;">
                                <label style="font-size:11px; margin:0;">
                                    <input type="checkbox" name="quod_active[<?php echo h($i); ?>]" value="1" <?php echo $active == 1 ? "checked" : ""; ?> style="width:auto; height:auto; margin-right:4px;"> Active
                                </label>
                            </td>
                            <td class="text-center" style="width:40px;">
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
                <button type="button" class="btn-classic" onclick="openSelectedHistory()" style="width: 80px; height:24px;">OS/PO</button>
            </div>
        </div>
    </form>

    <form id="deleteForm" method="post" action="quotation.php" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="quo_id" id="delete_quo_id" value="">
    </form>
</div>

<div id="acBox" class="ac-box"></div>

</body>
</html>