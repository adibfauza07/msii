<?php
// ==========================================================
// File: receive.php
// Deskripsi: Penerimaan Barang (ICL) - Ultimate Hybrid + Tombol Hapus Detail
// Kompabilitas: PHP 5.4, SQL Server 2008 (msdata & budget)
// ==========================================================

if (session_id() == "") session_start();
require_once 'config.php';
if (!isset($conn_msdata) || $conn_msdata === false) die("Koneksi database msdata gagal.");

$current_file = basename($_SERVER['PHP_SELF']); 

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function getv($n, $d="") { return isset($_GET[$n]) ? trim((string)$_GET[$n]) : $d; }
function postv($n, $d="") { return isset($_POST[$n]) ? trim((string)$_POST[$n]) : $d; }
function sql_error_text() { 
    $errors = sqlsrv_errors(); $err = ""; 
    if($errors) { foreach($errors as $e) { $err .= $e['message'] . "\n"; } } 
    return $err; 
}
function to_float($v) { $v = trim((string)$v); return $v==="" ? 0 : floatval(str_replace(",", "", $v)); }
function fmt_date($v) {
    if ($v instanceof DateTime) return $v->format("Y-m-d");
    if ($v === "" || $v === null) return date("Y-m-d");
    $t = strtotime((string)$v); return $t === false ? date("Y-m-d") : date("Y-m-d", $t);
}
function fmt_date_view($v) {
    if ($v instanceof DateTime) return $v->format("d-M-Y");
    if ($v === "" || $v === null) return "";
    $t = strtotime((string)$v); return $t === false ? "" : date("d-M-Y", $t);
}

function generate_rcv_no_msdata($conn_msdata) {
    $stmt = @sqlsrv_query($conn_msdata, "SELECT TOP 1 NEXT_PR, PR_NUM FROM dbo.REFS WHERE REF_ID = 1");
    if ($stmt !== false && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (isset($r["NEXT_PR"]) && trim($r["NEXT_PR"]) !== "") {
            return trim($r["NEXT_PR"]); 
        }
    }
    return date('ym') . '0001';
}

function inc_next_rcv_no_msdata($conn_msdata) {
    $sql = "UPDATE dbo.REFS SET NEXT_PR = (CAST(NEXT_PR AS INT) + 1) WHERE REF_ID = 1";
    @sqlsrv_query($conn_msdata, $sql);
}

$message = "";
$error = "";
$action = postv("action","");
$editId = intval(getv("edit","0"));
$source = getv("source", postv("doc_source", "PO")); 

/* ======================================================
   AKSI SPESIFIK DELPHI (KHUSUS MSDATA PO)
====================================================== */
if ($source === 'PO') {
    if ($action == "set_next_icl") {
        $nextVal = intval(postv("upd_rcv_id", "0")); 
        if ($nextVal > 0) {
            if (sqlsrv_query($conn_msdata, "UPDATE dbo.REFS SET NEXT_PR = ? WHERE REF_ID = 1", array($nextVal))) $message = "SET NEXT ICL BERHASIL"; else $error = "Gagal mengubah NEXT ICL:\n" . sql_error_text();
        }
    }
    if ($action == "trigger_off") {
        $sqlOff = "BEGIN ALTER TABLE [dbo].[RECEIVE_DETAIL] DISABLE TRIGGER add_inv_recvd; ALTER TABLE [dbo].[RECEIVE_DETAIL] DISABLE TRIGGER del_inv_recvd; ALTER TABLE [dbo].[RECEIVE_DETAIL] DISABLE TRIGGER upd_inv_recvd; END";
        if (sqlsrv_query($conn_msdata, $sqlOff)) $message = "TRIGGER OFF BERHASIL"; else $error = "Gagal mematikan trigger:\n".sql_error_text();
    }
    if ($action == "trigger_on") {
        $sqlOn = "BEGIN ALTER TABLE [dbo].[RECEIVE_DETAIL] ENABLE TRIGGER add_inv_recvd; ALTER TABLE [dbo].[RECEIVE_DETAIL] ENABLE TRIGGER del_inv_recvd; ALTER TABLE [dbo].[RECEIVE_DETAIL] ENABLE TRIGGER upd_inv_recvd; END";
        if (sqlsrv_query($conn_msdata, $sqlOn)) $message = "TRIGGER ON BERHASIL"; else $error = "Gagal menyalakan trigger:\n".sql_error_text();
    }
    if ($action == "update_price") {
        $stmtUpd = sqlsrv_query($conn_msdata, "EXEC sp_update_price_rec ?, ?, ?", array(intval(postv("upd_rcv_id")), intval(postv("upd_item_id")), intval(postv("upd_po_id"))));
        if ($stmtUpd === false) $error = "Update Price Gagal:\n".sql_error_text(); else $message = "UPDATE PRICE SELESAI";
    }
}

/* ======================================================
   DELETE RECEIVE (HYBRID)
====================================================== */
if ($action == "delete") {
    $rcvId = intval(postv("rcv_id","0"));
    if ($rcvId > 0) {
        if ($source === 'PO') {
            $stmtCek = sqlsrv_query($conn_msdata, "SELECT COUNT(*) AS CNT FROM dbo.RECEIVE_DETAIL WHERE RCV_ID = ?", array($rcvId));
            $rc = sqlsrv_fetch_array($stmtCek, SQLSRV_FETCH_ASSOC);
            if (intval($rc["CNT"]) > 0) {
                $error = "Gagal: Receive msdata masih memiliki detail. Hapus detail terlebih dahulu.";
            } else {
                sqlsrv_begin_transaction($conn_msdata);
                sqlsrv_query($conn_msdata, "DELETE FROM dbo.BC_TRANS WHERE NO_TRANS = (SELECT RCV_NO FROM dbo.RECEIVE WHERE RCV_ID = ?)", array($rcvId));
                if (sqlsrv_query($conn_msdata, "DELETE FROM dbo.RECEIVE WHERE RCV_ID = ?", array($rcvId))) {
                    sqlsrv_commit($conn_msdata); header("Location: " . $current_file . "?source=PO&msg=deleted"); exit;
                } else { sqlsrv_rollback($conn_msdata); $error = "Delete gagal:\n".sql_error_text(); }
            }
        } else {
            $sqlDel = "DELETE FROM Receive_Det WHERE receive_no = (SELECT receive_no FROM Receive_Header WHERE receive_id = ?); DELETE FROM Receive_Header WHERE receive_id = ?;";
            if (sqlsrv_query($conn, $sqlDel, array($rcvId, $rcvId))) {
                header("Location: " . $current_file . "?source=PR&msg=deleted"); exit;
            } else { $error = "Delete Budget gagal:\n".sql_error_text(); }
        }
    }
}

/* ======================================================
   SAVE RECEIVE (HYBRID: INSERT / UPDATE)
====================================================== */
if ($action == "save") {
    $is_success = true;
    $rcvId = intval(postv("rcv_id","0"));
    $isNewRecord = ($rcvId == 0);
    
    if ($source === 'PO') {
        $raw_rcv_no = postv("rcv_no", generate_rcv_no_msdata($conn_msdata));
        $rcvNo      = substr(strtoupper($raw_rcv_no), 0, 20); 
        $rcvDate    = postv("rcv_date", date("Y-m-d")) . ' ' . date('H:i:s');
        $rcvDono    = substr(postv("rcv_dono",""), 0, 100);
        $supId      = intval(postv("sup_id","0"));
        $rcvPic     = substr(postv("rcv_pic",""), 0, 15);
        $rcvType    = intval(postv("rcv_type","1"));
        $jenisBc    = substr(postv("jenis_bc",""), 0, 50);
        $nomorBc    = substr(postv("nomor_bc",""), 0, 50);
        $bcDate     = postv("bc_date", date("Y-m-d"));

        $poIds   = isset($_POST["po_id"]) ? $_POST["po_id"] : array();
        $itemIds = isset($_POST["item_id"]) ? $_POST["item_id"] : array();
        $qtys    = isset($_POST["rcvd_qty"]) ? $_POST["rcvd_qty"] : array();
        $prices  = isset($_POST["pod_price"]) ? $_POST["pod_price"] : array();

        if ($supId <= 0 || empty($rcvNo)) {
            $is_success = false; $error = "Supplier dan Nomor ICL wajib diisi.";
        } else {
            sqlsrv_begin_transaction($conn_msdata);
            $ok = true;

            if (!$isNewRecord) {
                $sqlH = "UPDATE dbo.RECEIVE SET RCV_NO=?, RCV_DONO=?, RCV_DATE=?, RCV_PIC=?, SUP_ID=?, RCV_TYPE=? WHERE RCV_ID=?";
                $stmtH = sqlsrv_query($conn_msdata, $sqlH, array($rcvNo, $rcvDono, $rcvDate, $rcvPic, $supId, $rcvType, $rcvId));
                if ($stmtH === false) { $ok=false; $error="Simpan header gagal:\n".sql_error_text(); }
            } else {
                $sqlH = "
                    SET NOCOUNT ON; 
                    INSERT INTO dbo.RECEIVE (RCV_NO, RCV_DONO, RCV_DATE, RCV_PIC, SUP_ID, RCV_TYPE) 
                    VALUES (?, ?, ?, ?, ?, ?); 
                    SELECT SCOPE_IDENTITY() AS ID;
                ";
                $stmtH = sqlsrv_query($conn_msdata, $sqlH, array($rcvNo, $rcvDono, $rcvDate, $rcvPic, $supId, $rcvType));
                
                if ($stmtH === false) { 
                    $ok=false; $error="Simpan header gagal (Data terlalu panjang):\n".sql_error_text(); 
                } else {
                    $rcvId = 0;
                    do {
                        while ($row_id = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC)) {
                            if (isset($row_id['ID']) && $row_id['ID'] != null) {
                                $rcvId = intval($row_id['ID']);
                                break 2;
                            }
                        }
                    } while (sqlsrv_next_result($stmtH));

                    if ($rcvId <= 0) { 
                        $ok=false; $error="Gagal mengambil ID Generate dari sistem database."; 
                    }
                }
            }

            if ($ok) {
                sqlsrv_query($conn_msdata, "DELETE FROM dbo.BC_TRANS WHERE NO_TRANS=?", array($rcvNo));
                if ($jenisBc != "" || $nomorBc != "") {
                    $stmtBc = sqlsrv_query($conn_msdata, "INSERT INTO dbo.BC_TRANS (NO_TRANS, JENIS_BC, NOMOR_BC, BC_DATE) VALUES (?, ?, ?, ?)", array($rcvNo, $jenisBc, $nomorBc, $bcDate));
                    if ($stmtBc === false) { $ok=false; $error="Simpan BC gagal:\n".sql_error_text(); }
                }
            }

            if ($ok && !$isNewRecord) {
                if (sqlsrv_query($conn_msdata, "DELETE FROM dbo.RECEIVE_DETAIL WHERE RCV_ID = ?", array($rcvId)) === false) { $ok=false; $error="Gagal hapus detail lama."; }
            }

            if ($ok) {
                $sqlD = "INSERT INTO dbo.RECEIVE_DETAIL (RCV_ID, ITEM_ID, PO_ID, RCVD_QTY, POD_PRICE) VALUES (?, ?, ?, ?, ?)";
                for ($i=0; $i<count($itemIds); $i++) {
                    if (isset($_POST['row_checked'][$i])) {
                        $qty = to_float($qtys[$i]);
                        if ($qty > 0) {
                            $stmtD = sqlsrv_query($conn_msdata, $sqlD, array($rcvId, intval($itemIds[$i]), intval($poIds[$i]), $qty, to_float($prices[$i])));
                            if ($stmtD === false) { $ok=false; $error="Simpan detail gagal (Trigger Inventory Error):\n".sql_error_text(); break; }
                        }
                    }
                }
            }

            if ($ok) {
                sqlsrv_commit($conn_msdata);
                if ($isNewRecord) { inc_next_rcv_no_msdata($conn_msdata); }
                header("Location: " . $current_file . "?source=PO&edit=".$rcvId."&msg=saved"); 
                exit;
            } else { sqlsrv_rollback($conn_msdata); }
        }

    } else {
        // --- JALUR BUDGET (PR) ---
        $deptId  = trim(postv("department_id", ""));
        $plantId = trim(postv("plant_id", ""));
        $rcvPic  = trim(postv("received_by", ""));
        $rcvDate = date('Y-m-d H:i:s');
        
        $prDetIds  = isset($_POST["pr_detail_id"]) ? $_POST["pr_detail_id"] : array();
        $itemCodes = isset($_POST["item_code"]) ? $_POST["item_code"] : array();
        $qtys      = isset($_POST["rcvd_qty"]) ? $_POST["rcvd_qty"] : array(); 

        if (empty($deptId) || count($prDetIds) == 0) {
            $is_success = false; $error = "Departemen dan minimal 1 item PR wajib diisi.";
        } else {
            sqlsrv_begin_transaction($conn);

            $prefix = 'RCV-' . date('ym');
            $sql_last = "SELECT TOP 1 receive_no FROM Receive_Header WHERE receive_no LIKE ? ORDER BY receive_no DESC";
            $stmt_last = sqlsrv_query($conn, $sql_last, array($prefix . '%'));
            $seq = 1;
            if ($stmt_last && sqlsrv_has_rows($stmt_last)) {
                $r_last = sqlsrv_fetch_array($stmt_last, SQLSRV_FETCH_ASSOC);
                $seq = (int)substr($r_last['receive_no'], -4) + 1;
            }
            $rcvNo = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

            if (!$isNewRecord) {
                $rcvNo = postv("rcv_no", $rcvNo); 
                $stmtH = sqlsrv_query($conn, "UPDATE Receive_Header SET receive_no=?, receive_date=?, department_id=?, plant_id=?, received_by=? WHERE receive_id=?", array($rcvNo, $rcvDate, $deptId, $plantId, $rcvPic, $rcvId));
                if ($stmtH === false) { $is_success=false; $error="Gagal Update PR Header:\n".sql_error_text(); }
            } else {
                $sqlH = "
                    SET NOCOUNT ON;
                    INSERT INTO Receive_Header (receive_no, receive_date, department_id, plant_id, received_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, GETDATE()); 
                    SELECT SCOPE_IDENTITY() AS ID;
                ";
                $stmtH = sqlsrv_query($conn, $sqlH, array($rcvNo, $rcvDate, $deptId, $plantId, $rcvPic));
                if ($stmtH) {
                    $rcvId = 0;
                    do {
                        while ($row_id = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC)) {
                            if (isset($row_id['ID']) && $row_id['ID'] != null) {
                                $rcvId = intval($row_id['ID']);
                                break 2;
                            }
                        }
                    } while (sqlsrv_next_result($stmtH));
                    
                    if ($rcvId <= 0) { $is_success = false; $error = "Gagal mengambil ID Generate sistem (PR)."; }
                } else { $is_success = false; $error = "Gagal Header PR:\n".sql_error_text(); }
            }

            if ($is_success && !$isNewRecord) {
                sqlsrv_query($conn, "DELETE FROM Receive_Det WHERE receive_no=?", array($rcvNo));
            }

            if ($is_success) {
                $sqlD = "INSERT INTO Receive_Det (receive_no, pr_detail_id, item_code, qty_in) VALUES (?, ?, ?, ?)";
                for ($i=0; $i<count($prDetIds); $i++) {
                    $qty = to_float($qtys[$i]);
                    if ($qty > 0 && isset($_POST['row_checked'][$i])) {
                        $stmtD = sqlsrv_query($conn, $sqlD, array($rcvNo, intval($prDetIds[$i]), trim($itemCodes[$i]), $qty));
                        if ($stmtD === false) { $is_success = false; $error = "Gagal Detail PR:\n".sql_error_text(); break; }
                    }
                }
            }

            if ($is_success) {
                sqlsrv_commit($conn); 
                header("Location: " . $current_file . "?source=PR&msg=saved&edit=".$rcvId); exit;
            } else { sqlsrv_rollback($conn); }
        }
    }
}

if (getv("msg") == "saved") $message = "Transaksi Penerimaan berhasil disimpan dan diposting ke Inventory.";

/* ======================================================
   LOAD HEADER & DETAILS UNTUK EDIT
====================================================== */
$header = array("RCV_ID"=>"", "RCV_NO"=>"", "RCV_DATE"=>date("Y-m-d"), "RCV_DONO"=>"", "SUP_ID"=>"", "SUP_CODE"=>"", "SUP_COMP"=>"", "RCV_PIC"=>"", "RCV_TYPE"=>"1", "JENIS_BC"=>"", "NOMOR_BC"=>"", "BC_DATE"=>date("Y-m-d"), "DEP_ID"=>"", "PLANT_ID"=>"P1");
$details = array();

if ($editId > 0) {
    if ($source === 'PO') {
        $sqlH = "SELECT R.RCV_ID, R.RCV_NO, R.RCV_DATE, R.RCV_DONO, R.SUP_ID, R.RCV_PIC, R.RCV_TYPE, S.SUP_CODE, S.SUP_COMP, B.JENIS_BC, B.NOMOR_BC, B.BC_DATE FROM dbo.RECEIVE R LEFT JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID LEFT JOIN dbo.BC_TRANS B ON R.RCV_NO = B.NO_TRANS WHERE R.RCV_ID = ?";
        $stmtH = sqlsrv_query($conn_msdata, $sqlH, array($editId));
        if ($stmtH !== false && $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC)) {
            foreach ($header as $k=>$v) if (isset($rh[$k])) $header[$k] = $rh[$k];
            $header["RCV_DATE"] = fmt_date($header["RCV_DATE"]); $header["BC_DATE"] = fmt_date($header["BC_DATE"]);
        }
        $sqlD = "SELECT D.ITEM_ID, D.PO_ID, D.RCVD_QTY AS os, D.POD_PRICE, I.ITEM_CODE, I.ITEM_NAME, P.PO_NUM, ISNULL(I.ITEM_UNIT, '-') AS uom FROM dbo.RECEIVE_DETAIL D LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID LEFT JOIN dbo.PO P ON D.PO_ID = P.PO_ID WHERE D.RCV_ID = ? ORDER BY I.ITEM_CODE";
        $stmtD = sqlsrv_query($conn_msdata, $sqlD, array($editId));
        if ($stmtD) while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) $details[] = $rd;
    } else {
        $sqlH = "SELECT receive_id AS RCV_ID, receive_no AS RCV_NO, receive_date AS RCV_DATE, department_id AS DEP_ID, plant_id AS PLANT_ID, received_by AS RCV_PIC FROM Receive_Header WHERE receive_id = ?";
        $stmtH = sqlsrv_query($conn, $sqlH, array($editId));
        if ($stmtH !== false && $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC)) {
            foreach ($header as $k=>$v) if (isset($rh[$k])) $header[$k] = $rh[$k];
            $header["RCV_DATE"] = fmt_date($header["RCV_DATE"]);
        }
        $sqlD = "SELECT D.pr_detail_id AS PO_ID, D.item_code AS ITEM_CODE, D.qty_in AS os, I.item_name AS ITEM_NAME, P.pr_no AS PO_NUM, I.uom FROM Receive_Det D LEFT JOIN Master_Item I ON D.item_code = I.item_code LEFT JOIN PR_Detail P ON D.pr_detail_id = P.pr_detail_id WHERE D.receive_no = ? ORDER BY D.item_code";
        $stmtD = sqlsrv_query($conn, $sqlD, array($header['RCV_NO']));
        if ($stmtD) while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) $details[] = $rd;
    }
} else {
    $header["RCV_NO"] = ($source === 'PO') ? generate_rcv_no_msdata($conn_msdata) : 'RCV-' . date('ym') . rand(1000, 9999);
}

// Dropdown Master Data
$sqlSup = "SELECT SUP_ID, SUP_CODE, SUP_COMP FROM dbo.SUPPLIER ORDER BY SUP_COMP ASC";
$stmtSup = sqlsrv_query($conn_msdata, $sqlSup);
$sqlDept = "SELECT department_id, department_name, plant_id FROM Master_Department WHERE is_active = 1 ORDER BY department_name ASC";
$stmtDept = sqlsrv_query($conn, $sqlDept);
$bcList = array();
$stmtBc = sqlsrv_query($conn_msdata,"SELECT BCTY_NAME FROM dbo.BCTY");
if ($stmtBc) { while ($b = sqlsrv_fetch_array($stmtBc, SQLSRV_FETCH_ASSOC)) $bcList[] = trim((string)$b["BCTY_NAME"]); }

/* ======================================================
   LIST GRID (Paginasi T-SQL 2008 ROW_NUMBER)
====================================================== */
$q = getv("q",""); $page = intval(getv("p", "1")); if ($page < 1) $page = 1; $limit = 50; 
$offsetStart = ($page - 1) * $limit + 1; $offsetEnd = $page * $limit;
$paramsData = array();

if ($source === 'PO') {
    $where = "WHERE 1=1";
    if ($q != "") { $where .= " AND (R.RCV_NO LIKE ? OR S.SUP_CODE LIKE ? OR S.SUP_COMP LIKE ? OR R.RCV_DONO LIKE ?)"; array_push($paramsData, "%$q%", "%$q%", "%$q%", "%$q%"); }
    $sqlList = "WITH CTE AS (
        SELECT R.RCV_ID, R.RCV_NO, R.RCV_DATE, R.RCV_PIC, R.RCV_DONO, S.SUP_COMP, COUNT(D.ITEM_ID) AS DET_CNT, ROW_NUMBER() OVER(ORDER BY R.RCV_DATE DESC, R.RCV_NO DESC) AS RN
        FROM dbo.RECEIVE R LEFT JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID LEFT JOIN dbo.RECEIVE_DETAIL D ON R.RCV_ID = D.RCV_ID $where GROUP BY R.RCV_ID, R.RCV_NO, R.RCV_DATE, R.RCV_PIC, R.RCV_DONO, S.SUP_COMP
    ) SELECT * FROM CTE WHERE RN BETWEEN ? AND ?";
    array_push($paramsData, $offsetStart, $offsetEnd);
    $stmtList = sqlsrv_query($conn_msdata, $sqlList, $paramsData);
} else {
    $where = "WHERE 1=1";
    if ($q != "") { $where .= " AND (R.receive_no LIKE ? OR R.received_by LIKE ?)"; array_push($paramsData, "%$q%", "%$q%"); }
    $sqlList = "WITH CTE AS (
        SELECT R.receive_id AS RCV_ID, R.receive_no AS RCV_NO, R.receive_date AS RCV_DATE, R.received_by AS RCV_PIC, R.department_id AS SUP_COMP, COUNT(D.item_code) AS DET_CNT, ROW_NUMBER() OVER(ORDER BY R.receive_date DESC, R.receive_no DESC) AS RN
        FROM Receive_Header R LEFT JOIN Receive_Det D ON R.receive_no = D.receive_no $where GROUP BY R.receive_id, R.receive_no, R.receive_date, R.received_by, R.department_id
    ) SELECT * FROM CTE WHERE RN BETWEEN ? AND ?";
    array_push($paramsData, $offsetStart, $offsetEnd);
    $stmtList = sqlsrv_query($conn, $sqlList, $paramsData);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Form Input Penerimaan Barang (ICL) - ERP Modern</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, Arial, sans-serif; font-size: 13px; }
        .top-navbar { background-color: #004d40; color: #fff; padding: 12px 20px; font-size: 18px; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .source-box { background: #fff; border: 2px dashed #004d40; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .header-panel { background-color: #006666; color: #e0f2f1; padding: 15px; border-radius: 6px; margin-bottom: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header-panel label { font-weight: bold; font-size: 12px; margin-bottom: 3px; }
        .bc-panel { background-color: #007e33; color: #e8f5e9; padding: 15px; border-radius: 6px; margin-bottom: 15px; }
        .btn-custom { font-size: 13px; font-weight: bold; padding: 6px 16px; border-radius: 4px; }
        .table th { background-color: #d9d9d9; vertical-align: middle; border: 1px solid #aaa; color: #333; font-weight: bold; }
        .table td { vertical-align: middle; }
        .grid-row:hover { background-color: #cce5ff; cursor: pointer; }
        .select2-container .select2-selection--single { height: 32px !important; border-radius: 4px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 30px !important; color: #333; font-weight: bold; }
        .action-box { border: 2px solid #cc0000; background: #fff; color: #cc0000; padding: 5px 15px; font-weight: bold; text-align: center; border-radius: 4px; }
    </style>
</head>
<body>

<div class="top-navbar">
    <i class="fas fa-boxes"></i> Form Input Penerimaan Barang (Goods Receipt)
</div>

<div class="container-fluid mt-3 px-4">
    <?php if ($message != "") { echo "<div class='alert alert-success shadow-sm'><strong><i class='fas fa-check-circle'></i></strong> $message</div>"; } ?>
    <?php if ($error != "") { echo "<div class='alert alert-danger shadow-sm'><strong><i class='fas fa-exclamation-triangle'></i></strong> <pre class='m-0'>$error</pre></div>"; } ?>

    <!-- TOGGLE SUMBER DOKUMEN -->
    <div class="source-box text-center">
        <label class="font-weight-bold d-block text-dark mb-3"><i class="fas fa-random"></i> PILIH SUMBER DATABASE RUJUKAN</label>
        <div class="custom-control custom-radio custom-control-inline">
            <input type="radio" id="srcPO" name="doc_source_nav" class="custom-control-input" value="PO" onchange="window.location.href='<?php echo $current_file; ?>?source=PO'" <?php echo $source=='PO'?'checked':''; ?>>
            <label class="custom-control-label text-primary font-weight-bold" for="srcPO" style="cursor: pointer;"><i class="fas fa-shopping-cart"></i> Purchase Order (MSDATA)</label>
        </div>
        <div class="custom-control custom-radio custom-control-inline">
            <input type="radio" id="srcPR" name="doc_source_nav" class="custom-control-input" value="PR" onchange="window.location.href='<?php echo $current_file; ?>?source=PR'" <?php echo $source=='PR'?'checked':''; ?>>
            <label class="custom-control-label text-success font-weight-bold" for="srcPR" style="cursor: pointer;"><i class="fas fa-file-invoice"></i> Budgeting / PR (Non-PO)</label>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="mb-3">
        <button class="btn btn-light btn-custom border shadow-sm" onclick="window.location.href='<?php echo $current_file; ?>?source=<?php echo $source; ?>'"><i class="fas fa-file"></i> Baru / Reset</button>
        <button class="btn btn-success btn-custom shadow-sm" onclick="document.getElementById('rcvForm').submit()"><i class="fas fa-save"></i> Simpan Transaksi</button>
        <?php if($editId > 0) { ?>
            <button class="btn btn-danger btn-custom shadow-sm" onclick="deleteCurrent()"><i class="fas fa-trash"></i> Hapus</button>
        <?php } ?>
        <a href="dashboard_purchasing.php" class="btn btn-dark btn-custom shadow-sm float-right"><i class="fas fa-times"></i> Tutup</a>
    </div>

    <!-- FORM UTAMA TRANSAKSI -->
    <form id="rcvForm" method="post" action="<?php echo $current_file; ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="doc_source" value="<?php echo h($source); ?>">
        <input type="hidden" name="rcv_id" id="rcv_id" value="<?php echo h($header["RCV_ID"]); ?>">

        <!-- PANEL HEADER -->
        <div class="header-panel">
            <div class="row">
                <?php if ($source === 'PO'): ?>
                    <div class="col-md-3 mb-2">
                        <label>I.C.L No. (Bisa ditambah karakter di belakang)</label>
                        <input type="text" name="rcv_no" class="form-control font-weight-bold text-dark" value="<?php echo h($header["RCV_NO"]); ?>" <?php echo ($editId > 0) ? 'readonly' : ''; ?> placeholder="Contoh: 26080939/WMI">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label>Date</label>
                        <input type="date" name="rcv_date" class="form-control" value="<?php echo h($header["RCV_DATE"]); ?>" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label>D.O # (Surat Jalan)</label>
                        <input type="text" name="rcv_dono" class="form-control" value="<?php echo h($header["RCV_DONO"]); ?>">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label>Supplier Company <span class="text-warning">*</span></label>
                        <select name="sup_id" id="supSelect" class="form-control select2-search" required>
                            <option value="">-- Pilih Supplier --</option>
                            <?php if ($stmtSup) while ($s = sqlsrv_fetch_array($stmtSup, SQLSRV_FETCH_ASSOC)) echo '<option value="'.$s['SUP_ID'].'" '.($header['SUP_ID']==$s['SUP_ID']?'selected':'').'>'.h($s['SUP_CODE'].' - '.$s['SUP_COMP']).'</option>'; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="col-md-4 mb-2">
                        <label>Departemen <span class="text-warning">*</span></label>
                        <select name="department_id" id="deptSelect" class="form-control select2-search" required>
                            <option value="">-- Pilih Dept --</option>
                            <?php if ($stmtDept) while ($d = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)) echo '<option value="'.h($d['department_id']).'" data-plant="'.h($d['plant_id']).'" '.($header['DEP_ID']==$d['department_id']?'selected':'').'>'.h($d['department_name']).'</option>'; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label>Plant</label>
                        <input type="text" name="plant_id" id="plantDisplay" class="form-control bg-light text-dark font-weight-bold" readonly value="<?php echo h($header['PLANT_ID']); ?>" required>
                    </div>
                    <div class="col-md-5 mb-2" id="prWrapper" style="display:none;">
                        <label>Pilih Nomor PR (Multiple) <span class="text-warning">*</span></label>
                        <select id="prSelect" class="form-control select2-multiple" multiple="multiple"></select>
                    </div>
                    <input type="hidden" name="rcv_no" value="<?php echo h($header["RCV_NO"]); ?>">
                <?php endif; ?>
            </div>

            <div class="row mt-2">
                <div class="col-md-4 mb-0">
                    <label>PIC (Diterima Oleh) <span class="text-warning">*</span></label>
                    <input type="text" name="<?php echo $source==='PO'?'rcv_pic':'received_by'; ?>" class="form-control" required value="<?php echo h($header["RCV_PIC"]); ?>" placeholder="Nama Penerima">
                </div>
                <?php if ($source === 'PO'): ?>
                <div class="col-md-3 mb-0">
                    <label>Type Penerimaan</label>
                    <select name="rcv_type" class="form-control">
                        <option value="1" <?php echo $header["RCV_TYPE"]=="1"?"selected":""; ?>>Material - Supplier</option>
                        <option value="2" <?php echo $header["RCV_TYPE"]=="2"?"selected":""; ?>>Spare Part - Supplier</option>
                        <option value="3" <?php echo $header["RCV_TYPE"]=="3"?"selected":""; ?>>Part - Vendor</option>
                        <option value="4" <?php echo $header["RCV_TYPE"]=="4"?"selected":""; ?>>Others</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($source === 'PO'): ?>
        <!-- Panel Bea Cukai -->
        <div class="bc-panel shadow-sm">
            <div class="row">
                <div class="col-md-3"><label>Jenis BC</label><select name="jenis_bc" class="form-control"><option value="">- Pilih BC -</option><?php foreach($bcList as $b) echo '<option value="'.h($b).'" '.($header["JENIS_BC"]==$b?"selected":"").'>'.h($b).'</option>'; ?></select></div>
                <div class="col-md-4"><label>Nomor BC</label><input type="text" name="nomor_bc" class="form-control" value="<?php echo h($header["NOMOR_BC"]); ?>"></div>
                <div class="col-md-3"><label>Tanggal BC</label><input type="date" name="bc_date" class="form-control" value="<?php echo h($header["BC_DATE"]); ?>"></div>
            </div>
        </div>

        <!-- Toolbar Cetak Delphi -->
        <div class="mb-3 border-bottom pb-2">
            <button type="button" class="btn btn-info btn-sm font-weight-bold shadow-sm mr-1" onclick="cetakICL()"><i class="fas fa-print"></i> Cetak ICL</button>
            <button type="button" class="btn btn-primary btn-sm font-weight-bold shadow-sm mr-1" onclick="cetakICLOto()"><i class="fas fa-print"></i> ICL OTO</button>
            <button type="button" class="btn btn-warning btn-sm font-weight-bold shadow-sm mr-1" onclick="cetakSchedule()"><i class="fas fa-calendar"></i> Sch Material</button>
            <button type="button" class="btn btn-dark btn-sm font-weight-bold shadow-sm float-right" onclick="setNextICL()">SET NEXT ICL</button>
        </div>
        <?php endif; ?>

        <!-- DETAIL TABLE (LIVE SEARCH) -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 border">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Rincian Item Barang (<?php echo $source==='PO' ? 'Outstanding PO Open' : 'PR Approved'; ?>)</h6>
                <div class="input-group input-group-sm" style="width: 250px;">
                    <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search"></i></span></div>
                    <input type="text" id="searchItemBox" class="form-control" placeholder="Cari kode atau nama...">
                </div>
            </div>
            
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-bordered mb-0" id="tableDetail">
                    <thead class="text-center" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <?php if ($source === 'PO'): ?>
                                <th width="5%">Pilih</th><th width="15%">Item Code</th><th width="30%">Item Name</th><th width="15%">PO Number</th><th width="10%">OS</th><th width="15%">Qty Terima</th><th width="10%">Harga</th><th width="5%">Aksi</th>
                            <?php else: ?>
                                <th width="15%">No. PR</th><th width="15%">ID Detail PR</th><th width="35%">Kode & Nama Barang</th><th width="15%">Qty Diminta</th><th width="15%">Qty Diterima <span class="text-danger">*</span></th><th width="5%">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="detailBody">
                        <?php 
                        if (count($details) > 0) {
                            for ($i=0; $i<count($details); $i++) {
                                $d = $details[$i];
                                $qty = isset($d["os"]) ? floatval($d["os"]) : 0;
                                $price = isset($d["POD_PRICE"]) ? floatval($d["POD_PRICE"]) : 0;
                                
                                echo '
                                <tr class="item-row" style="background-color: #e8f5e9;">
                                    <td class="text-center">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input row-checkbox" id="chk_'.$i.'" name="row_checked['.$i.']" checked>
                                            <label class="custom-control-label" for="chk_'.$i.'"></label>
                                        </div>
                                        <input type="hidden" name="po_id[]" value="'.h($d["PO_ID"]).'" class="param-input">
                                        '.($source==='PO'?'<input type="hidden" name="item_id[]" value="'.h($d["ITEM_ID"]).'" class="param-input">':'<input type="hidden" name="item_code[]" value="'.h($d["ITEM_CODE"]).'" class="param-input">').'
                                        <input type="hidden" name="pod_price[]" value="'.h($price).'" class="param-input">
                                    </td>
                                    <td class="item-code"><strong>'.h($d["ITEM_CODE"]).'</strong></td>
                                    <td class="item-name">'.h($d["ITEM_NAME"]).' <small>('.h(isset($d["uom"])?$d["uom"]:'').')</small></td>
                                    <td class="text-center text-primary font-weight-bold">'.h($d["PO_NUM"]).'</td>
                                    <td class="text-center">-</td>
                                    <td><input type="number" name="rcvd_qty[]" class="form-control form-control-sm text-right font-weight-bold text-success qty-input" value="'.h($qty).'" step="any" required></td>
                                    '.($source==='PO'?'<td class="text-right">'.number_format($price,0,',','.').'</td>':'').'
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove" title="Hapus Baris"><i class="fas fa-times"></i></button></td>
                                </tr>';
                            }
                        } else {
                            $msgTarget = $source === 'PO' ? 'Supplier Company' : 'Departemen';
                            echo '<tr id="emptyRow"><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-box-open fa-3x mb-3 text-secondary"></i><br>Silakan isi parameter <strong>'.$msgTarget.'</strong> di atas untuk memuat daftar barang.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($source === 'PO'): ?>
        <!-- TRIGGER DELPHI MSDATA -->
        <div class="row align-items-center mb-5">
            <div class="col-md-6">
                <button type="button" class="btn btn-danger btn-sm font-weight-bold shadow-sm" onclick="triggerAction('trigger_off')">TRIGGER OFF</button>
                <button type="button" class="btn btn-warning btn-sm font-weight-bold shadow-sm mx-2" onclick="triggerUpdatePrice()">UPDATE PRICE (SP)</button>
                <button type="button" class="btn btn-success btn-sm font-weight-bold shadow-sm" onclick="triggerAction('trigger_on')">TRIGGER ON</button>
            </div>
            <div class="col-md-6 text-right">
                <div class="action-box d-inline-block shadow-sm">JANGAN LUPA KLIK TOMBOL ON LAGI SETELAH KLIK TOMBOL OFF</div>
            </div>
        </div>
        <?php else: echo '<div class="mt-4"></div>'; endif; ?>
    </form>

    <!-- FORM TERSEMBUNYI UNTUK AKSI KHUSUS -->
    <form id="deleteForm" method="post" action="<?php echo $current_file; ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="doc_source" value="<?php echo h($source); ?>">
        <input type="hidden" name="rcv_id" id="delete_rcv_id" value="">
    </form>

    <form id="execForm" method="post" action="<?php echo $current_file; ?>">
        <input type="hidden" name="action" id="exec_action" value="">
        <input type="hidden" name="doc_source" value="<?php echo h($source); ?>">
        <input type="hidden" name="upd_rcv_id" id="upd_rcv_id" value="">
        <input type="hidden" name="upd_item_id" id="upd_item_id" value="">
        <input type="hidden" name="upd_po_id" id="upd_po_id" value="">
        <input type="hidden" name="edit" value="<?php echo h($editId); ?>">
    </form>

    <!-- DATA GRID HISTORIS -->
    <div class="card shadow-sm mt-2 mb-5">
        <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
            <span><i class="fas fa-table"></i> Historis Data Receive (<?php echo h($source); ?>)</span>
            <form method="get" action="<?php echo $current_file; ?>" class="form-inline m-0">
                <input type="hidden" name="source" value="<?php echo h($source); ?>">
                <input type="text" name="q" class="form-control form-control-sm mr-2" style="width: 250px;" value="<?php echo h($q); ?>" placeholder="Pencarian Bebas...">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> SEARCH</button>
                <a href="<?php echo $current_file; ?>?source=<?php echo h($source); ?>" class="btn btn-secondary btn-sm ml-2">ALL</a>
            </form>
        </div>
        <div class="table-responsive" style="height: 300px;">
            <table class="table table-bordered table-sm mb-0 bg-white table-hover">
                <thead class="text-center" style="position: sticky; top: 0; background: #e9ecef; z-index: 1;">
                    <tr>
                        <th width="5%">Aksi</th>
                        <th>RCV NO</th>
                        <th>Date</th>
                        <th>PIC</th>
                        <th><?php echo $source==='PO'?'DO #':'Plant'; ?></th>
                        <th><?php echo $source==='PO'?'Supplier':'Departemen'; ?></th>
                        <th>Item Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($stmtList) && $stmtList !== false) { while($r=sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)){ ?>
                    <tr class="grid-row" onclick="goEdit('<?php echo intval($r["RCV_ID"]); ?>')">
                        <td class="text-center text-primary"><i class="fas fa-edit"></i></td>
                        <td class="font-weight-bold text-success"><?php echo h($r["RCV_NO"]); ?></td>
                        <td class="text-center"><?php echo h(fmt_date_view($r["RCV_DATE"])); ?></td>
                        <td><?php echo h($r["RCV_PIC"]); ?></td>
                        <td class="text-center"><?php echo h(isset($r["RCV_DONO"])?$r["RCV_DONO"]:'P1'); ?></td>
                        <td><?php echo h($r["SUP_COMP"]); ?></td>
                        <td class="text-center font-weight-bold text-danger"><?php echo h($r["DET_CNT"]); ?></td>
                    </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer py-2 text-right bg-light">
            <a href="<?php echo $current_file; ?>?source=<?php echo h($source); ?>&p=<?php echo ($page > 1 ? $page - 1 : 1); ?>&q=<?php echo urlencode($q); ?>" class="btn btn-sm btn-outline-secondary">&laquo; Prev</a>
            <span class="px-3 font-weight-bold">Page <?php echo $page; ?></span>
            <a href="<?php echo $current_file; ?>?source=<?php echo h($source); ?>&p=<?php echo $page + 1; ?>&q=<?php echo urlencode($q); ?>" class="btn btn-sm btn-outline-secondary">Next &raquo;</a>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-search').select2({ width: '100%' });
    $('.select2-multiple').select2({ placeholder: "-- Pilih Nomor PR --", allowClear: true, width: '100%' });

    var isEditMode = <?php echo ($editId > 0) ? 'true' : 'false'; ?>;
    var docSource = '<?php echo $source; ?>';

    // ===============================================
    // JALUR MSDATA (PO) - TRIGGER AJAX
    // ===============================================
    if (docSource === 'PO') {
        $('#supSelect').on('change', function() {
            if (isEditMode) return; 
            var supId = $(this).val();
            $('#searchItemBox').val('');

            if (!supId) {
                $('#detailBody').html('<tr id="emptyRow"><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-box-open fa-3x mb-3 text-secondary"></i><br>Silakan pilih <strong>Supplier Company</strong> di atas.</td></tr>');
                return;
            }

            $('#detailBody').html('<tr><td colspan="8" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-info"></i><br>Memuat data outstanding...</td></tr>');

            $.ajax({
                url: 'get_open_po_items.php', type: 'GET', data: { sup_id: supId }, dataType: 'json',
                success: function(data) {
                    if (data && data.error) {
                        $('#detailBody').html('<tr><td colspan="8" class="text-center text-danger py-4 font-weight-bold"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>' + data.error + '</td></tr>');
                        return;
                    }

                    if (data.length > 0) {
                        var rows = '';
                        $.each(data, function(i, row) {
                            rows += `
                                <tr class="item-row">
                                    <td class="text-center">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input row-checkbox" id="chk_${i}" name="row_checked[${i}]" value="${i}">
                                            <label class="custom-control-label" for="chk_${i}"></label>
                                        </div>
                                        <input type="hidden" name="po_id[]" value="${row.po_id}" disabled class="param-input">
                                        <input type="hidden" name="item_id[]" value="${row.item_id}" disabled class="param-input">
                                        <input type="hidden" name="pod_price[]" value="${row.pod_price}" disabled class="param-input">
                                    </td>
                                    <td class="item-code"><strong>${row.item_code}</strong></td>
                                    <td class="item-name">${row.item_name} <small class="text-muted">(${row.uom})</small></td>
                                    <td class="text-center text-primary font-weight-bold">${row.po_num}</td>
                                    <td class="text-center font-weight-bold text-danger">${row.os}</td>
                                    <td><input type="number" name="rcvd_qty[]" class="form-control form-control-sm text-right font-weight-bold text-success qty-input" min="0.01" max="${row.os}" step="any" value="${row.os}" disabled required></td>
                                    <td class="text-right">${Number(row.pod_price).toLocaleString('id-ID')}</td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove" title="Hapus Baris"><i class="fas fa-times"></i></button></td>
                                </tr>
                            `;
                        });
                        $('#detailBody').html(rows);
                    } else {
                        $('#detailBody').html('<tr><td colspan="8" class="text-center text-danger py-4 font-weight-bold">Semua item PO dari supplier ini telah terpenuhi (OS = 0) atau belum ada.</td></tr>');
                    }
                },
                error: function(xhr, status, error) {
                    var errText = xhr.responseText ? xhr.responseText.substring(0, 100) : error;
                    $('#detailBody').html('<tr><td colspan="8" class="text-center text-danger py-4 font-weight-bold"><i class="fas fa-wifi fa-2x mb-2"></i><br>Gagal terhubung ke server API.<br><small>' + errText + '</small></td></tr>');
                }
            });
        });

        $(document).on('change', '.row-checkbox', function() {
            var tr = $(this).closest('tr');
            var inputs = tr.find('.param-input, .qty-input');
            if ($(this).is(':checked')) { inputs.prop('disabled', false); tr.css('background-color', '#e8f5e9'); } 
            else { inputs.prop('disabled', true); tr.css('background-color', ''); }
        });

        // Event Tombol Hapus Baris Detail
        $(document).on('click', '.btn-remove', function() {
            $(this).closest('tr').remove();
            if ($('#detailBody tr').length === 0) {
                $('#detailBody').html('<tr id="emptyRow"><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-box-open fa-3x mb-3 text-secondary"></i><br>Belum ada rincian item barang.</td></tr>');
            }
        });

        $('#rcvForm').on('submit', function(e) {
            if ($('.row-checkbox:checked').length === 0) { e.preventDefault(); alert("Centang minimal 1 barang untuk diterima."); return false; }
        });
    }

    // ===============================================
    // JALUR BUDGET (PR) - TRIGGER AJAX
    // ===============================================
    if (docSource === 'PR') {
        $('#deptSelect').on('change', function() {
            if (isEditMode) return;
            var selected = $(this).find('option:selected');
            var deptId = $(this).val();
            
            if(selected.data('plant')) $('#plantDisplay').val(selected.data('plant') + ' - Plant ' + selected.data('plant').replace('P','')); 
            else $('#plantDisplay').val('');
            
            $('#prSelect').val(null).trigger('change');
            $('#detailBody').html('<tr id="emptyRow"><td colspan="6" class="text-center text-muted py-5">Silakan pilih Nomor PR.</td></tr>');

            if (deptId !== "") {
                $('#prWrapper').fadeIn();
                $.ajax({
                    url: 'get_pr_by_dept.php', type: 'GET', data: { dept_id: deptId }, dataType: 'json',
                    success: function(data) {
                        var options = '';
                        $.each(data, function(i, item) { options += '<option value="' + item.pr_no + '">' + item.text + '</option>'; });
                        $('#prSelect').html(options).trigger('change');
                    },
                    error: function() { alert("Gagal mengambil data departemen."); }
                });
            } else { $('#prWrapper').hide(); }
        });

        $('#prSelect').on('change', function() {
            var selectedPRs = $(this).val(); 
            if (!selectedPRs || selectedPRs.length === 0) { $('#detailBody').html('<tr id="emptyRow"><td colspan="6" class="text-center text-muted py-5">Belum ada PR yang dipilih.</td></tr>'); return; }
            
            $('#detailBody').html('<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-info"></i><br>Memuat rincian PR...</td></tr>');
            
            $.ajax({
                url: 'get_pr_details.php', type: 'GET', data: { pr_nos: selectedPRs.join(',') }, dataType: 'json',
                success: function(data) {
                    if (data && data.error) {
                        $('#detailBody').html('<tr><td colspan="6" class="text-center text-danger py-4">' + data.error + '</td></tr>'); return;
                    }
                    var rows = '';
                    if (data.length > 0) {
                        $.each(data, function(i, row) {
                            rows += `
                                <tr class="item-row">
                                    <td><strong>${row.pr_no}</strong></td>
                                    <td>${row.pr_detail_id}<input type="hidden" name="po_id[]" value="${row.pr_detail_id}"><input type="hidden" name="row_checked[${i}]" value="1"></td>
                                    <td class="item-code item-name">${row.item_code} - ${row.item_name} (${row.uom})<input type="hidden" name="item_code[]" value="${row.item_code}"></td>
                                    <td class="text-center font-weight-bold">${row.qty_request}</td>
                                    <td><input type="number" name="rcvd_qty[]" class="form-control form-control-sm text-right font-weight-bold text-success" min="0.01" max="${row.qty_request}" step="any" value="${row.qty_request}" required></td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove" title="Hapus Baris"><i class="fas fa-times"></i></button></td>
                                </tr>
                            `;
                        });
                        $('#detailBody').html(rows);
                    } else { $('#detailBody').html('<tr><td colspan="6" class="text-center text-danger py-4">Detail barang PR tidak ditemukan.</td></tr>'); }
                },
                error: function() { $('#detailBody').html('<tr><td colspan="6" class="text-center text-danger py-4">Gagal terhubung ke API PR.</td></tr>'); }
            });
        });

        $(document).on('click', '.btn-remove', function() {
            $(this).closest('tr').remove();
            if ($('#detailBody tr').length === 0) $('#detailBody').html('<tr id="emptyRow"><td colspan="6" class="text-center text-muted py-5">Belum ada PR yang dipilih.</td></tr>');
        });

        $('#rcvForm').on('submit', function(e) {
            if ($('#detailBody tr#emptyRow').length > 0 || $('#detailBody tr').length === 0) { e.preventDefault(); alert("Harap pilih minimal 1 PR."); return false; }
        });
    }

    // ===============================================
    // LIVE SEARCH AUTO COMPLETE
    // ===============================================
    $('#searchItemBox').on('keyup', function() {
        var keyword = $(this).val().toLowerCase().trim();
        $('#detailBody tr.item-row').each(function() {
            var row = $(this);
            var itemCode = row.find('.item-code').text().toLowerCase();
            var itemName = row.find('.item-name').text().toLowerCase();
            if (itemCode.indexOf(keyword) > -1 || itemName.indexOf(keyword) > -1) { row.show(); } else { row.hide(); }
        });
    });

});

// ===============================================
// FUNGSI AKSI DELPHI KLASIK
// ===============================================
var currentFile = '<?php echo $current_file; ?>';
var currentSource = '<?php echo $source; ?>';

function newData() { window.location.href = currentFile + "?source=" + currentSource; }
function goEdit(id) { if(!id)return; window.location.href = currentFile + "?source=" + currentSource + "&edit=" + id; }
function deleteCurrent() { 
    var id = $('#rcv_id').val(); 
    if(!id || id=="0"){alert("Pilih Data Receive dulu di tabel Historis bawah."); return;} 
    if(confirm("Yakin hapus transaksi Receive ini secara permanen?")){ $('#delete_rcv_id').val(id); $('#deleteForm').submit(); } 
}

function triggerAction(actionName) {
    if(!confirm("Jalankan aksi " + actionName.toUpperCase() + " pada Database?")) return;
    $('#exec_action').val(actionName); $('#execForm').submit();
}

function triggerUpdatePrice() {
    var firstChecked = $('.row-checkbox:checked').first().closest('tr');
    if (firstChecked.length === 0) { alert("Pilih / centang baris detail item di tabel."); return; }
    
    var itemId = firstChecked.find('input[name="item_id[]"]').val();
    var poId = firstChecked.find('input[name="po_id[]"]').val();
    var rcvId = $('#rcv_id').val();
    
    if(!rcvId || rcvId == "0") { alert("Simpan Receive terlebih dahulu sebelum melakukan update harga (SP)!"); return; }
    if(!itemId || !poId) { alert("Data item tidak valid."); return; }
    
    if(confirm("Jalankan SP Update Price untuk item yang dipilih?")) {
        $('#exec_action').val("update_price");
        $('#upd_item_id').val(itemId);
        $('#upd_po_id').val(poId);
        $('#upd_rcv_id').val(rcvId); 
        $('#execForm').submit();
    }
}

function setNextICL() {
    var nextNum = prompt("Masukkan nilai antrian untuk ICL selanjutnya:", "");
    if (nextNum !== null && nextNum.trim() !== "" && !isNaN(nextNum)) {
        $('#exec_action').val("set_next_icl"); $('#upd_rcv_id').val(nextNum); $('#execForm').submit();
    } else { alert("Format harus berupa angka valid!"); }
}

// FUNGSI PRINT
function cetakICL() { var id=$('input[name="rcv_no"]').val(); if(id) window.open((currentSource==='PO'?'report_penerimaan_msdata.php':'report_penerimaan.php')+"?id="+encodeURIComponent(id), "_blank", "width=900,height=700"); else alert("Simpan data dulu!"); }
function cetakICLOto() { var id=$('input[name="rcv_no"]').val(); if(id) window.open("print_icl_oto.php?id="+encodeURIComponent(id), "_blank", "width=900,height=700"); else alert("Simpan data dulu!"); }
function cetakSchedule() { var no=$('input[name="rcv_no"]').val(); if(no) window.open("print_schedule.php?no="+no.substring(0,6), "_blank", "width=900,height=700"); else alert("Data kosong!"); }
</script>

</body>
</html>