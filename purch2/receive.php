<?php
if (session_id() == "") session_start();

require_once dirname(__DIR__) . "/config/db_plant2.php";
if ($conn === false) die("Koneksi database gagal.");

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function getv($n, $d="") { return isset($_GET[$n]) ? trim((string)$_GET[$n]) : $d; }
function postv($n, $d="") { return isset($_POST[$n]) ? trim((string)$_POST[$n]) : $d; }
function sql_error_text() { return print_r(sqlsrv_errors(), true); }
function to_float($v) { $v = trim((string)$v); return $v==="" ? 0 : floatval(str_replace(",", "", $v)); }

function fmt_date($v) {
    if ($v instanceof DateTime) return $v->format("Y-m-d");
    if ($v === "" || $v === null) return date("Y-m-d");
    $t = strtotime((string)$v);
    return $t === false ? date("Y-m-d") : date("Y-m-d", $t);
}

function fmt_date_view($v) {
    if ($v instanceof DateTime) return $v->format("d-M-Y");
    if ($v === "" || $v === null) return "";
    $t = strtotime((string)$v);
    return $t === false ? "" : date("d-M-Y", $t);
}

function generate_rcv_no($conn) {
    $ret = array("NEXT_PR"=>1, "PR_NUM"=>"%s");
    $stmt = @sqlsrv_query($conn, "SELECT TOP 1 NEXT_PR, PR_NUM FROM dbo.REFS WHERE REF_ID = 1");
    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r) {
            if (isset($r["NEXT_PR"])) $ret["NEXT_PR"] = intval($r["NEXT_PR"]);
            if (isset($r["PR_NUM"]) && trim((string)$r["PR_NUM"]) !== "") $ret["PR_NUM"] = trim((string)$r["PR_NUM"]);
        }
    }
    return trim(sprintf($ret["PR_NUM"], str_pad($ret["NEXT_PR"], 6, "0", STR_PAD_LEFT)));
}

function inc_next_rcv_no($conn) {
    @sqlsrv_query($conn, "UPDATE TOP (1) dbo.REFS SET NEXT_PR = ISNULL(NEXT_PR, 0) + 1 WHERE REF_ID = 1");
}

$message = "";
$error = "";
$action = postv("action","");
$editId = intval(getv("edit","0"));

// Deteksi AJAX murni via URL atau POST
$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1') || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');

/* ======================================================
   AKSI SPESIFIK: SET NEXT ICL, TRIGGER ON/OFF, UPDATE PRICE 
====================================================== */
if ($action == "set_next_icl") {
    $nextVal = intval(postv("upd_rcv_id", "0")); 
    if ($nextVal > 0) {
        $sqlSet = "UPDATE dbo.REFS SET NEXT_PR = ? WHERE REF_ID = 1";
        if (sqlsrv_query($conn, $sqlSet, array($nextVal))) {
            $message = "SET NEXT ICL BERHASIL diubah menjadi: " . $nextVal;
        } else {
            $error = "Gagal mengubah NEXT ICL:\n" . sql_error_text();
        }
    }
}

if ($action == "trigger_off") {
    $sqlOff = "BEGIN 
               ALTER TABLE [dbo].[receive_detail] DISABLE TRIGGER add_inv_recvd; 
               ALTER TABLE [dbo].[receive_detail] DISABLE TRIGGER del_inv_recvd; 
               ALTER TABLE [dbo].[receive_detail] DISABLE TRIGGER upd_inv_recvd; 
               END";
    if (sqlsrv_query($conn, $sqlOff)) $message = "TRIGGER OFF BERHASIL";
    else $error = "Gagal mematikan trigger:\n".sql_error_text();
}

if ($action == "trigger_on") {
    $sqlOn = "BEGIN 
              ALTER TABLE [dbo].[receive_detail] ENABLE TRIGGER add_inv_recvd; 
              ALTER TABLE [dbo].[receive_detail] ENABLE TRIGGER del_inv_recvd; 
              ALTER TABLE [dbo].[receive_detail] ENABLE TRIGGER upd_inv_recvd; 
              END";
    if (sqlsrv_query($conn, $sqlOn)) $message = "TRIGGER ON BERHASIL";
    else $error = "Gagal menyalakan trigger:\n".sql_error_text();
}

if ($action == "update_price") {
    $updRcvId = intval(postv("upd_rcv_id", "0"));
    $updItemId = intval(postv("upd_item_id", "0"));
    $updPoId = intval(postv("upd_po_id", "0"));
    
    $stmtUpd = sqlsrv_query($conn, "EXEC sp_update_price_rec ?, ?, ?", array($updRcvId, $updItemId, $updPoId));
    if ($stmtUpd === false) $error = "Update Price Gagal:\n".sql_error_text();
    else $message = "UPDATE PRICE SELESAI";
}

/* ======================================================
   --- FITUR PINDAH ICL ---
====================================================== */
// 1. PINDAH KE ICL EXISTING (YANG SUDAH ADA)
if ($action == "move_detail") {
    $sourceRcvId = intval(postv("rcv_id", "0"));
    $targetRcvId = intval(postv("target_rcv_id", "0"));
    $moveItems = isset($_POST["move_check"]) ? $_POST["move_check"] : array();

    if ($sourceRcvId <= 0 || $targetRcvId <= 0) {
        $error = "Pilih ICL Sumber dan pastikan ICL Tujuan valid.";
    } elseif ($sourceRcvId == $targetRcvId) {
        $error = "ICL Tujuan tidak boleh sama dengan ICL saat ini.";
    } elseif (count($moveItems) == 0) {
        $error = "Centang minimal 1 detail yang akan dipindah.";
    } else {
        sqlsrv_begin_transaction($conn);
        $ok = true;
        foreach ($moveItems as $val) {
            $arr = explode("|", $val);
            if (count($arr) == 2) {
                $mItemId = intval($arr[0]);
                $mPoId = intval($arr[1]);
                $sqlMv = "UPDATE dbo.RECEIVE_DETAIL SET RCV_ID = ? WHERE RCV_ID = ? AND ITEM_ID = ? AND PO_ID = ?";
                $stmtMv = sqlsrv_query($conn, $sqlMv, array($targetRcvId, $sourceRcvId, $mItemId, $mPoId));
                if ($stmtMv === false) { $ok = false; $error = "Gagal pindah detail:\n".sql_error_text(); break; }
            }
        }
        if ($ok) {
            sqlsrv_commit($conn);
            header("Location: receive.php?edit=".$sourceRcvId."&msg=moved");
            exit;
        } else {
            sqlsrv_rollback($conn);
        }
    }
}

// 2. PINDAH KE ICL BARU (AUTO GENERATE NEW ICL)
if ($action == "move_to_new") {
    $sourceRcvId = intval(postv("rcv_id", "0"));
    $moveItems = isset($_POST["move_check"]) ? $_POST["move_check"] : array();

    if ($sourceRcvId <= 0) {
        $error = "Pilih ICL Sumber valid.";
    } elseif (count($moveItems) == 0) {
        $error = "Centang minimal 1 detail yang akan dipindah ke ICL Baru.";
    } else {
        sqlsrv_begin_transaction($conn);
        $ok = true;

        $sqlSrc = "SELECT * FROM dbo.RECEIVE WHERE RCV_ID = ?";
        $stmtSrc = sqlsrv_query($conn, $sqlSrc, array($sourceRcvId));
        $srcHdr = sqlsrv_fetch_array($stmtSrc, SQLSRV_FETCH_ASSOC);

        if (!$srcHdr) {
            $ok = false; $error = "Data ICL sumber tidak ditemukan.";
        } else {
            $newRcvNo = generate_rcv_no($conn);
            $sqlH = "
                SET NOCOUNT ON;
                DECLARE @OutputTbl TABLE (NEW_ID INT);
                INSERT INTO dbo.RECEIVE (RCV_NO, RCV_DONO, RCV_DATE, RCV_PIC, SUP_ID, RCV_TYPE) 
                OUTPUT INSERTED.RCV_ID INTO @OutputTbl
                VALUES (?, ?, ?, ?, ?, ?);
                SELECT NEW_ID FROM @OutputTbl;
            ";
            $stmtH = sqlsrv_query($conn, $sqlH, array(
                $newRcvNo, $srcHdr['RCV_DONO'], $srcHdr['RCV_DATE'], 
                $srcHdr['RCV_PIC'], $srcHdr['SUP_ID'], $srcHdr['RCV_TYPE']
            ));

            $newRcvId = 0;
            if ($stmtH === false) {
                $ok=false; $error="Buat header ICL baru gagal:\n".sql_error_text();
            } else {
                $new = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
                if ($new && isset($new['NEW_ID'])) {
                    $newRcvId = intval($new['NEW_ID']);
                } else {
                    if (sqlsrv_next_result($stmtH)) {
                        $new = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
                        if ($new && isset($new['NEW_ID'])) $newRcvId = intval($new['NEW_ID']);
                    }
                }
            }

            if ($newRcvId <= 0) {
                $ok = false;
                if($error == "") $error = "Gagal mendapatkan ID ICL baru.";
            }

            if ($ok) {
                foreach ($moveItems as $val) {
                    $arr = explode("|", $val);
                    if (count($arr) == 2) {
                        $mItemId = intval($arr[0]);
                        $mPoId = intval($arr[1]);
                        $sqlMv = "UPDATE dbo.RECEIVE_DETAIL SET RCV_ID = ? WHERE RCV_ID = ? AND ITEM_ID = ? AND PO_ID = ?";
                        $stmtMv = sqlsrv_query($conn, $sqlMv, array($newRcvId, $sourceRcvId, $mItemId, $mPoId));
                        if ($stmtMv === false) { $ok = false; $error = "Gagal pindah detail ke ICL baru:\n".sql_error_text(); break; }
                    }
                }
            }

            if ($ok) {
                inc_next_rcv_no($conn);
            }
        }

        if ($ok) {
            sqlsrv_commit($conn);
            header("Location: receive.php?edit=".$newRcvId."&msg=moved_new");
            exit;
        } else {
            sqlsrv_rollback($conn);
        }
    }
}

/* ======================================================
   DELETE RECEIVE
====================================================== */
if ($action == "delete") {
    $rcvId = intval(postv("rcv_id","0"));
    if ($rcvId <= 0) {
        $error = "Pilih Receive dulu.";
    } else {
        $stmtCek = sqlsrv_query($conn, "SELECT COUNT(*) AS CNT FROM dbo.RECEIVE_DETAIL WHERE RCV_ID = ?", array($rcvId));
        if ($stmtCek !== false) {
            $rc = sqlsrv_fetch_array($stmtCek, SQLSRV_FETCH_ASSOC);
            if (intval($rc["CNT"]) > 0) {
                $error = "Tidak dapat menghapus, Receive masih memiliki detail.";
            } else {
                sqlsrv_begin_transaction($conn);
                $ok = true;
                
                $stmtBc = sqlsrv_query($conn, "DELETE FROM dbo.BC_TRANS WHERE NO_TRANS = (SELECT RCV_NO FROM dbo.RECEIVE WHERE RCV_ID = ?)", array($rcvId));
                if ($stmtBc === false) $ok = false;
                
                if ($ok) {
                    $stmtDel = sqlsrv_query($conn, "DELETE FROM dbo.RECEIVE WHERE RCV_ID = ?", array($rcvId));
                    if ($stmtDel === false) $ok = false;
                }
                
                if ($ok) {
                    sqlsrv_commit($conn);
                    header("Location: receive.php?msg=deleted"); exit;
                } else {
                    sqlsrv_rollback($conn);
                    $error = "Delete Receive gagal:\n".sql_error_text();
                }
            }
        }
    }
}

/* ======================================================
   SAVE RECEIVE
====================================================== */
if ($action == "save") {
    $rcvId = intval(postv("rcv_id","0"));
    $rcvNo = strtoupper(postv("rcv_no",""));
    $rcvDate = postv("rcv_date",date("Y-m-d"));
    $rcvDono = postv("rcv_dono","");
    $supId = intval(postv("sup_id","0"));
    $rcvPic = postv("rcv_pic","");
    $rcvType = postv("rcv_type","1");
    
    $jenisBc = postv("jenis_bc","");
    $nomorBc = postv("nomor_bc","");
    $bcDate = postv("bc_date",date("Y-m-d"));

    $allowEmpty = intval(postv("allow_empty_detail","0"));

    if ($rcvDate == "") $rcvDate = date("Y-m-d");

    if ($rcvNo == "") {
        $rcvNo = generate_rcv_no($conn);
    }

    $isNewRecord = ($rcvId == 0);

    if ($supId <= 0) $error = "Supplier wajib dipilih.";

    if ($error == "") {
        $itemIds = isset($_POST["item_id"]) ? $_POST["item_id"] : array();
        $poIds = isset($_POST["po_id"]) ? $_POST["po_id"] : array();
        $qtys = isset($_POST["rcvd_qty"]) ? $_POST["rcvd_qty"] : array();
        $prices = isset($_POST["pod_price"]) ? $_POST["pod_price"] : array();

        $hasDetail = false;
        for ($i=0; $i<count($itemIds); $i++) {
            $itemId = intval($itemIds[$i]);
            $qty = isset($qtys[$i]) ? to_float($qtys[$i]) : 0;
            if ($itemId > 0 && $qty > 0) { $hasDetail = true; break; }
        }

        if (!$hasDetail && !($allowEmpty == 1 && $rcvId > 0)) {
            $error = "Detail Receive minimal 1 baris dan qty harus lebih dari 0.";
        } else {
            sqlsrv_begin_transaction($conn);
            $ok = true;

            if (!$isNewRecord) {
                $sqlH = "UPDATE dbo.RECEIVE SET RCV_NO=?, RCV_DONO=?, RCV_DATE=?, RCV_PIC=?, SUP_ID=?, RCV_TYPE=? WHERE RCV_ID=?";
                $stmtH = sqlsrv_query($conn, $sqlH, array($rcvNo, $rcvDono, $rcvDate, $rcvPic, $supId, $rcvType, $rcvId));
                if ($stmtH === false) { $ok=false; $error="Simpan header gagal:\n".sql_error_text(); }
            } else {
                $sqlH = "
                    SET NOCOUNT ON;
                    DECLARE @OutputTbl TABLE (NEW_ID INT);
                    INSERT INTO dbo.RECEIVE (RCV_NO, RCV_DONO, RCV_DATE, RCV_PIC, SUP_ID, RCV_TYPE) 
                    OUTPUT INSERTED.RCV_ID INTO @OutputTbl
                    VALUES (?, ?, ?, ?, ?, ?);
                    SELECT NEW_ID FROM @OutputTbl;
                ";
                $stmtH = sqlsrv_query($conn, $sqlH, array($rcvNo, $rcvDono, $rcvDate, $rcvPic, $supId, $rcvType));
                
                if ($stmtH === false) {
                    $ok=false; $error="Simpan header gagal:\n".sql_error_text();
                } else {
                    $new = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
                    if ($new && isset($new['NEW_ID'])) {
                        $rcvId = intval($new['NEW_ID']);
                    } else { 
                        if (sqlsrv_next_result($stmtH)) {
                            $new = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
                            if ($new && isset($new['NEW_ID'])) {
                                $rcvId = intval($new['NEW_ID']);
                            } else {
                                $ok=false; $error="RCV_ID baru tidak terbaca (Next Result).";
                            }
                        } else {
                            $ok=false; $error="RCV_ID baru tidak terbaca."; 
                        }
                    }
                }
            }

            if ($ok) {
                sqlsrv_query($conn, "DELETE FROM dbo.BC_TRANS WHERE NO_TRANS=?", array($rcvNo));
                if ($jenisBc != "" || $nomorBc != "") {
                    $sqlBC = "INSERT INTO dbo.BC_TRANS (NO_TRANS, JENIS_BC, NOMOR_BC, BC_DATE) VALUES (?, ?, ?, ?)";
                    $stmtBc = sqlsrv_query($conn, $sqlBC, array($rcvNo, $jenisBc, $nomorBc, $bcDate));
                    if ($stmtBc === false) { $ok=false; $error="Simpan BC gagal:\n".sql_error_text(); }
                }
            }

            if ($ok) {
                $stmtDel = sqlsrv_query($conn, "DELETE FROM dbo.RECEIVE_DETAIL WHERE RCV_ID = ?", array($rcvId));
                if ($stmtDel === false) { $ok=false; $error="Hapus detail lama gagal:\n".sql_error_text(); }
            }

            if ($ok) {
                for ($i=0; $i<count($itemIds); $i++) {
                    $itemId = intval($itemIds[$i]);
                    $poId = intval($poIds[$i]);
                    $qty = isset($qtys[$i]) ? to_float($qtys[$i]) : 0;
                    $price = isset($prices[$i]) ? to_float($prices[$i]) : 0;

                    if ($itemId <= 0 || $poId <= 0 || $qty <= 0) continue;

                    $sqlD = "INSERT INTO dbo.RECEIVE_DETAIL (RCV_ID, ITEM_ID, PO_ID, RCVD_QTY, POD_PRICE) VALUES (?, ?, ?, ?, ?)";
                    $stmtD = sqlsrv_query($conn, $sqlD, array($rcvId, $itemId, $poId, $qty, $price));
                    if ($stmtD === false) { $ok=false; $error="Simpan detail gagal:\n".sql_error_text(); break; }
                }
            }

            if ($ok) {
                sqlsrv_commit($conn);
                if ($isNewRecord) inc_next_rcv_no($conn); 
                
                // ==== JIKA DISIMPAN LEWAT ENTER (AJAX) ====
                if ($isAjax) {
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(array("status" => "success", "rcv_id" => $rcvId));
                    exit;
                }
                // ==========================================

                header("Location: receive.php?edit=".$rcvId."&msg=saved");
                exit;
            } else {
                sqlsrv_rollback($conn);
                if ($error == "") $error = "Simpan Receive gagal:\n".sql_error_text();

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
   LOAD HEADER & DETAILS
====================================================== */
$header = array(
    "RCV_ID"=>"", "RCV_NO"=>"", "RCV_DATE"=>date("Y-m-d"), "RCV_DONO"=>"",
    "SUP_ID"=>"", "SUP_CODE"=>"", "SUP_COMP"=>"", "RCV_PIC"=>"", "RCV_TYPE"=>"1",
    "JENIS_BC"=>"", "NOMOR_BC"=>"", "BC_DATE"=>date("Y-m-d")
);
$details = array();

if ($editId > 0) {
    $sqlH = "
        SELECT R.RCV_ID, R.RCV_NO, R.RCV_DATE, R.RCV_DONO, R.SUP_ID, R.RCV_PIC, R.RCV_TYPE,
               S.SUP_CODE, S.SUP_COMP, B.JENIS_BC, B.NOMOR_BC, B.BC_DATE
        FROM dbo.RECEIVE R
        LEFT JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID
        LEFT JOIN dbo.BC_TRANS B ON R.RCV_NO = B.NO_TRANS
        WHERE R.RCV_ID = ?
    ";
    $stmtH = sqlsrv_query($conn,$sqlH,array($editId));
    if ($stmtH !== false && $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC)) {
        foreach ($header as $k=>$v) if (isset($rh[$k])) $header[$k] = $rh[$k];
        $header["RCV_DATE"] = fmt_date($header["RCV_DATE"]);
        $header["BC_DATE"] = fmt_date($header["BC_DATE"]);
    }

    $sqlD = "
        SELECT D.RCV_ID, D.ITEM_ID, D.PO_ID, D.RCVD_QTY, D.POD_PRICE,
               I.ITEM_CODE, I.ITEM_NAME, P.PO_NUM
        FROM dbo.RECEIVE_DETAIL D
        LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID
        LEFT JOIN dbo.PO P ON D.PO_ID = P.PO_ID
        WHERE D.RCV_ID = ?
        ORDER BY I.ITEM_CODE
    ";
    $stmtD = sqlsrv_query($conn,$sqlD,array($editId));
    if ($stmtD !== false) while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) $details[] = $rd;
} else {
    $header["RCV_NO"] = generate_rcv_no($conn);
}

if (getv("msg") == "saved") $message = "Receive berhasil disimpan.";
if (getv("msg") == "deleted") $message = "Receive berhasil dihapus.";
if (getv("msg") == "moved") $message = "Detail berhasil dipindahkan ke ICL tujuan.";
if (getv("msg") == "moved_new") $message = "Berhasil membuat ICL Baru dan memindahkan detail terpilih!";

/* ======================================================
   SUPPLIER & DEPT DATA (Untuk Autocomplete)
====================================================== */
$supplierAuto = array();
$sqlSup = "SELECT TOP 1000 SUP_ID, SUP_CODE, SUP_COMP FROM dbo.SUPPLIER WHERE ISNULL(SUP_CODE,'') <> '' ORDER BY SUP_CODE";
$stmtSup = sqlsrv_query($conn,$sqlSup);
if ($stmtSup !== false) {
    while ($s = sqlsrv_fetch_array($stmtSup, SQLSRV_FETCH_ASSOC)) {
        $supplierAuto[] = array("SUP_ID"=>intval($s["SUP_ID"]), "SUP_CODE"=>trim((string)$s["SUP_CODE"]), "SUP_COMP"=>trim((string)$s["SUP_COMP"]));
    }
}

$deptAuto = array();
$stmtDept = sqlsrv_query($conn,"SELECT DEP_ID, DEP_CODE, DEP_NAME FROM dbo.DEPT ORDER BY DEP_CODE");
if ($stmtDept !== false) {
    while ($d = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)) {
        $deptAuto[] = array("DEP_NAME"=>trim((string)$d["DEP_NAME"]));
    }
}

$bcList = array();
$stmtBc = sqlsrv_query($conn,"SELECT BCTY_NAME FROM dbo.BCTY");
if ($stmtBc !== false) {
    while ($b = sqlsrv_fetch_array($stmtBc, SQLSRV_FETCH_ASSOC)) $bcList[] = trim((string)$b["BCTY_NAME"]);
}

/* ======================================================
   RECEIVE AUTO COMPLETE DATA
====================================================== */
$rcvAuto = array();
$sqlRcvAuto = "
    SELECT TOP 1000 R.RCV_ID, R.RCV_NO, R.RCV_DONO, S.SUP_CODE, S.SUP_COMP
    FROM dbo.RECEIVE R
    LEFT JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID
    ORDER BY R.RCV_DATE DESC, R.RCV_NO DESC
";
$stmtRcvAuto = sqlsrv_query($conn, $sqlRcvAuto);
if ($stmtRcvAuto !== false) {
    while ($ra = sqlsrv_fetch_array($stmtRcvAuto, SQLSRV_FETCH_ASSOC)) {
        $rcvAuto[] = array(
            "RCV_ID" => intval($ra["RCV_ID"]),
            "RCV_NO" => trim((string)$ra["RCV_NO"]),
            "RCV_DONO" => trim((string)$ra["RCV_DONO"]),
            "SUP_CODE" => trim((string)$ra["SUP_CODE"]),
            "SUP_COMP" => trim((string)$ra["SUP_COMP"])
        );
    }
}

/* LIST RECEIVE */
$q = getv("q","");
$where = "";
$paramsList = array();
if ($q != "") {
    $where = "WHERE R.RCV_NO LIKE ? OR S.SUP_CODE LIKE ? OR S.SUP_COMP LIKE ? OR R.RCV_DONO LIKE ?";
    $paramsList = array("%".$q."%","%".$q."%","%".$q."%","%".$q."%");
}

$sqlList = "
    SELECT TOP 300 R.RCV_ID, R.RCV_NO, R.RCV_DATE, R.RCV_DONO, R.RCV_PIC, S.SUP_CODE, S.SUP_COMP,
           COUNT(D.ITEM_ID) AS DETAIL_COUNT
    FROM dbo.RECEIVE R
    LEFT JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID
    LEFT JOIN dbo.RECEIVE_DETAIL D ON R.RCV_ID = D.RCV_ID
    $where
    GROUP BY R.RCV_ID, R.RCV_NO, R.RCV_DATE, R.RCV_DONO, R.RCV_PIC, S.SUP_CODE, S.SUP_COMP
    ORDER BY R.RCV_DATE DESC, R.RCV_NO DESC
";
$stmtList = sqlsrv_query($conn,$sqlList,$paramsList);

if (count($details) == 0) {
    for ($i=0; $i<10; $i++) { // Render 10 blank rows initial to match screenshot
        $details[] = array("ITEM_ID"=>"","PO_ID"=>"","ITEM_CODE"=>"","ITEM_NAME"=>"","PO_NUM"=>"","RCVD_QTY"=>"","POD_PRICE"=>"");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Items Receive</title>

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

        .teal-header {
            background-color: #008080;
            padding: 5px 10px;
            color: #ffff00;
        }

        .lbl-yellow {
            color: #ffff00;
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
            transition: background-color 0.3s;
        }

        .input-classic:read-only { background-color: #e0e0e0; }
        
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
        .btn-action-wide { width: 80px; margin-right: 2px; }
        .btn-action-side { margin-bottom: 3px; min-width: 80px; }

        .fieldset-control {
            border: 1px solid #fff;
            padding: 6px;
            margin-top: 5px;
            position: relative;
        }
        
        .fieldset-control legend {
            color: #fff;
            font-size: 11px;
            background: #008080;
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
            transition: background-color 0.3s;
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
        .ac-item:hover, .ac-item.active { background: #008080; color: #ffffff; }

        /* Bootstrap grid system utilitas (minimalis) */
        .row { display: flex; flex-wrap: wrap; margin-right: -5px; margin-left: -5px; }
        .col-2 { flex: 0 0 16.666%; max-width: 16.666%; padding: 0 5px; }
        .col-3 { flex: 0 0 25%; max-width: 25%; padding: 0 5px; }
        .col-4 { flex: 0 0 33.333%; max-width: 33.333%; padding: 0 5px; }
        .col-6 { flex: 0 0 50%; max-width: 50%; padding: 0 5px; }
        .col-8 { flex: 0 0 66.666%; max-width: 66.666%; padding: 0 5px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>

    <script>
        var supplierData = <?php echo json_encode($supplierAuto) ? json_encode($supplierAuto) : '[]'; ?>;
        var deptData = <?php echo json_encode($deptAuto) ? json_encode($deptAuto) : '[]'; ?>;
        var rcvData = <?php echo json_encode($rcvAuto) ? json_encode($rcvAuto) : '[]'; ?>;
        
        var acBox=null, acItems=[], acIndex=-1, acMode="", acRow=-1;
        var rowSeq=<?php echo count($details); ?>;
        var selectedDetailRow=null;

        function byId(id){return document.getElementById(id);}
        function setValue(id,value){var el=byId(id); if(el) el.value=(value==null?"":value);}

        function initAC(){acBox=byId("acBox");}
        function hideAC(){
            if(acBox){acBox.style.display="none";acBox.innerHTML="";}
            acItems=[];acIndex=-1;acMode="";acRow=-1;
        }
        function positionAC(input){
            initAC();var r=input.getBoundingClientRect();
            acBox.style.left=(r.left+(window.scrollX||window.pageXOffset))+"px";
            acBox.style.top=(r.bottom+(window.scrollY||window.pageYOffset))+"px";
            acBox.style.width=(r.width<300?300:r.width)+"px";
        }
        function renderAC(renderText,pickFunc){
            initAC();acBox.innerHTML="";
            for(var i=0;i<acItems.length;i++){
                var d=document.createElement("div");
                d.className="ac-item"+(i==acIndex?" active":"");
                d.innerHTML=renderText(acItems[i]);
                d.setAttribute("data-index",i);
                d.onmousedown=function(){pickFunc(acItems[parseInt(this.getAttribute("data-index"),10)]);};
                acBox.appendChild(d);
            }
            acBox.style.display=acItems.length>0?"block":"none";
        }
        function acMove(step,renderText,pickFunc){
            if(acItems.length<=0)return;
            acIndex+=step;
            if(acIndex<0)acIndex=acItems.length-1;
            if(acIndex>=acItems.length)acIndex=0;
            renderAC(renderText,pickFunc);
        }
        function acEnter(pickFunc){
            if(acItems.length<=0)return false;
            if(acIndex<0)acIndex=0;
            pickFunc(acItems[acIndex]);
            return true;
        }

        function submitSave(allowEmpty){
            if(byId("allow_empty_detail")) byId("allow_empty_detail").value=allowEmpty?"1":"0";
            var f=byId("rcvForm");if(f) f.submit();
        }
        function newData(){window.location.href="receive.php";}
        function goEdit(id){if(!id)return;window.location.href="receive.php?edit="+encodeURIComponent(id);}
        function deleteCurrent(){
            var id=byId("rcv_id").value;
            if(id==""||id=="0"){alert("Pilih Receive dulu.");return;}
            if(!confirm("Yakin hapus Receive ini?"))return;
            byId("delete_rcv_id").value=id;byId("deleteForm").submit();
        }
        function headerKey(e){
            if(e.key==="Enter"){
                if(acMode!==""&&acItems.length>0)return true;
                e.preventDefault();submitSave(false);return false;
            }
        }

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
        
        // ==== FUNGSI SIMPAN AJAX (MENGGUNAKAN XMLHTTPREQUEST) ====
        function fieldEnterSave(e, inputEl){
            if(e.key==="Enter"){
                e.preventDefault();
                
                var form = byId("rcvForm");
                
                if(typeof FormData === "undefined" || typeof XMLHttpRequest === "undefined") {
                    submitSave(false);
                    return false;
                }

                var formData = new FormData(form);
                formData.append("is_ajax", "1"); 
                
                var oldBg = inputEl.style.backgroundColor;
                inputEl.style.backgroundColor = "#fff9c4"; 
                
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "receive.php?ajax=1", true);
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var data = JSON.parse(xhr.responseText);
                                if (data.status === "success") {
                                    inputEl.style.backgroundColor = "#c8e6c9"; 
                                    setTimeout(function(){ inputEl.style.backgroundColor = oldBg; }, 1000);
                                    
                                    var currId = byId("rcv_id").value;
                                    if(currId === "" || currId === "0") {
                                        byId("rcv_id").value = data.rcv_id;
                                        if(window.history && window.history.replaceState) {
                                            window.history.replaceState(null, null, "?edit=" + data.rcv_id);
                                        }
                                    }
                                } else {
                                    alert("Gagal Simpan:\n" + data.message);
                                    inputEl.style.backgroundColor = "#ffcdd2"; 
                                }
                            } catch (err) {
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
                
                // --- Kunci Perbaikan: Tambah Baris Baru Secara Otomatis ---
                focusNextRow(inputEl); 
                
                return false;
            }
        }
        // =============================================

        function selectDetailRow(tr){
            var rows=document.querySelectorAll("#detailBody tr");
            for(var i=0;i<rows.length;i++) rows[i].className = rows[i].className.replace(" detail-selected", "");
            selectedDetailRow=tr;
            if(tr) tr.className += " detail-selected";
        }

        // Receive Search AutoComplete (Header)
        function showRcvAC(input){
            initAC();
            var key=(input.value||"").toUpperCase();
            acMode="rcv"; acItems=[]; acIndex=-1;
            if(key.length<1){hideAC();return;}
            
            for(var i=0; i<rcvData.length; i++){
                var r = rcvData[i];
                var t = (r.RCV_NO||"")+" "+(r.RCV_DONO||"")+" "+(r.SUP_CODE||"")+" "+(r.SUP_COMP||"");
                if(t.toUpperCase().indexOf(key) >= 0) acItems.push(r);
                if(acItems.length >= 40) break;
            }
            positionAC(input);
            renderAC(function(r){
                return "<b>" + r.RCV_NO + "</b> - DO: " + r.RCV_DONO + "<br><small style='color:#666;'>" + r.SUP_CODE + " - " + r.SUP_COMP + "</small>";
            }, pickRcv);
        }
        function rcvKey(e,input){
            if(e.key==="ArrowDown"){
                e.preventDefault(); if(acMode!=="rcv"||acItems.length==0) showRcvAC(input);
                acMove(1, function(r){ return "<b>" + r.RCV_NO + "</b> - DO: " + r.RCV_DONO + "<br><small style='color:#666;'>" + r.SUP_CODE + " - " + r.SUP_COMP + "</small>"; }, pickRcv); return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault(); if(acMode!=="rcv"||acItems.length==0) showRcvAC(input);
                acMove(-1, function(r){ return "<b>" + r.RCV_NO + "</b> - DO: " + r.RCV_DONO + "<br><small style='color:#666;'>" + r.SUP_CODE + " - " + r.SUP_COMP + "</small>"; }, pickRcv); return false;
            }
            if(e.key==="Enter"){
                if(acMode==="rcv"&&acItems.length>0){e.preventDefault(); acEnter(pickRcv); return false;}
                return true; 
            }
            if(e.key==="Escape") hideAC();
        }
        function pickRcv(r){
            hideAC();
            goEdit(r.RCV_ID);
        }

        // Target RCV (Pindah ICL Bawah)
        function showTargetRcvAC(input){
            initAC();
            var key=(input.value||"").toUpperCase();
            acMode="target_rcv"; acItems=[]; acIndex=-1;
            if(key.length<1){hideAC();return;}
            
            for(var i=0; i<rcvData.length; i++){
                var r = rcvData[i];
                var t = (r.RCV_NO||""); 
                if(t.toUpperCase().indexOf(key) >= 0) acItems.push(r);
                if(acItems.length >= 40) break;
            }
            positionAC(input);
            renderAC(function(r){
                return "<b>" + r.RCV_NO + "</b>";
            }, pickTargetRcv);
        }
        function targetRcvKey(e,input){
            if(e.key==="ArrowDown"){
                e.preventDefault(); if(acMode!=="target_rcv"||acItems.length==0) showTargetRcvAC(input);
                acMove(1, function(r){ return "<b>" + r.RCV_NO + "</b>"; }, pickTargetRcv); return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault(); if(acMode!=="target_rcv"||acItems.length==0) showTargetRcvAC(input);
                acMove(-1, function(r){ return "<b>" + r.RCV_NO + "</b>"; }, pickTargetRcv); return false;
            }
            if(e.key==="Enter"){
                if(acMode==="target_rcv"&&acItems.length>0){e.preventDefault(); acEnter(pickTargetRcv); return false;}
                return true; 
            }
            if(e.key==="Escape") hideAC();
        }
        function pickTargetRcv(r){
            setValue("target_rcv_id", r.RCV_ID);
            setValue("target_rcv_no", r.RCV_NO);
            hideAC();
        }

        // Supplier Autocomplete
        function showSupplierAC(input){
            initAC();
            var key=(input.value||"").toUpperCase();
            acMode="supplier";acItems=[];acIndex=-1;
            if(key.length<1){hideAC();return;}
            for(var i=0;i<supplierData.length;i++){
                var s=supplierData[i];var t=(s.SUP_CODE||"")+" "+(s.SUP_COMP||"");
                if(t.toUpperCase().indexOf(key)>=0)acItems.push(s);
                if(acItems.length>=40)break;
            }
            positionAC(input);
            renderAC(function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);
        }
        function supplierKey(e,input){
            if(e.key==="ArrowDown"){
                e.preventDefault();if(acMode!=="supplier"||acItems.length==0)showSupplierAC(input);
                acMove(1,function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault();if(acMode!=="supplier"||acItems.length==0)showSupplierAC(input);
                acMove(-1,function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);return false;
            }
            if(e.key==="Enter"){
                if(acMode==="supplier"&&acItems.length>0){e.preventDefault();acEnter(pickSupplier);return false;}
                e.preventDefault();submitSave(false);return false;
            }
            if(e.key==="Escape")hideAC();
        }
        function pickSupplier(s){
            setValue("sup_id",s.SUP_ID);setValue("sup_code",s.SUP_CODE);setValue("sup_comp",s.SUP_COMP);hideAC();
        }

        // PIC / Dept Autocomplete
        function showPicAC(input){
            initAC();
            var key=(input.value||"").toUpperCase();
            acMode="pic";acItems=[];acIndex=-1;
            if(key.length<1){hideAC();return;}
            for(var i=0;i<deptData.length;i++){
                var d=deptData[i];
                if((d.DEP_NAME||"").toUpperCase().indexOf(key)>=0)acItems.push(d);
                if(acItems.length>=40)break;
            }
            positionAC(input);
            renderAC(function(d){return d.DEP_NAME;},pickPic);
        }
        function picKey(e,input){
            if(e.key==="ArrowDown"){
                e.preventDefault();if(acMode!=="pic"||acItems.length==0)showPicAC(input);
                acMove(1,function(d){return d.DEP_NAME;},pickPic);return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault();if(acMode!=="pic"||acItems.length==0)showPicAC(input);
                acMove(-1,function(d){return d.DEP_NAME;},pickPic);return false;
            }
            if(e.key==="Enter"){
                if(acMode==="pic"&&acItems.length>0){e.preventDefault();acEnter(pickPic);return false;}
                e.preventDefault();return false;
            }
            if(e.key==="Escape")hideAC();
        }
        function pickPic(d){setValue("rcv_pic",d.DEP_NAME);hideAC();}

        // PO Item Autocomplete
        function showPOItemAC(input, row) {
            initAC();
            var supId = byId("sup_id").value;
            if(!supId || supId == "0") { alert("Pilih supplier dulu."); input.value = ""; return; }
            
            var key = (input.value || "").toUpperCase();
            acMode = "poitem"; acRow = row; acItems = []; acIndex = -1;
            if(key.length < 1){ hideAC(); return; }

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "search_po_item.php", true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    try {
                        acItems = JSON.parse(xhr.responseText);
                        positionAC(input);
                        renderAC(function(o){ return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | PO: "+o.PO_NUM+" | O/S: "+o.OS_QTY; }, pickPOItem);
                    } catch(e) {}
                }
            };
            xhr.send("q=" + encodeURIComponent(key) + "&sup_id=" + encodeURIComponent(supId));
        }

        function poItemKey(e,input,row){
            if(e.key==="ArrowDown"){
                e.preventDefault();if(acMode!=="poitem"||acItems.length==0)showPOItemAC(input,row);
                acMove(1,function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | PO: "+o.PO_NUM+" | O/S: "+o.OS_QTY;},pickPOItem);return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault();if(acMode!=="poitem"||acItems.length==0)showPOItemAC(input,row);
                acMove(-1,function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | PO: "+o.PO_NUM+" | O/S: "+o.OS_QTY;},pickPOItem);return false;
            }
            if(e.key==="Enter"){
                if(acMode==="poitem"&&acItems.length>0){e.preventDefault();acEnter(pickPOItem);return false;}
                e.preventDefault();return false;
            }
            if(e.key==="Escape")hideAC();
        }
        function pickPOItem(o){
            var row=acRow;
            setValue("item_id_"+row,o.ITEM_ID);setValue("po_id_"+row,o.PO_ID);
            setValue("item_code_"+row,o.ITEM_CODE);setValue("item_name_"+row,o.ITEM_NAME);
            setValue("po_num_"+row,o.PO_NUM);setValue("rcvd_qty_"+row,o.OS_QTY);
            setValue("pod_price_"+row,o.POD_PRICE);
            hideAC();
            var q=byId("rcvd_qty_"+row);if(q){q.focus();q.select();}
        }

        // PO Number Autocomplete
        function showPONumAC(input, row) {
            initAC();
            var supId = byId("sup_id").value;
            var itemId = byId("item_id_" + row).value;
            
            if(!supId || supId == "0") { alert("Pilih supplier dulu."); input.value = ""; return; }
            if(!itemId || itemId == "") { alert("Pilih item dulu sebelum mencari PO."); input.value = ""; return; }
            
            var key = (input.value || "").toUpperCase();
            acMode = "ponum"; acRow = row; acItems = []; acIndex = -1;
            if(key.length < 1){ hideAC(); return; }

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "search_po_num.php", true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    try {
                        acItems = JSON.parse(xhr.responseText);
                        positionAC(input);
                        renderAC(function(o){ return "<b>PO: "+o.PO_NUM+"</b> | Price: "+o.POD_PRICE+" | O/S: "+o.OS_QTY; }, pickPONum);
                    } catch(e) {}
                }
            };
            xhr.send("q=" + encodeURIComponent(key) + "&sup_id=" + encodeURIComponent(supId) + "&item_id=" + encodeURIComponent(itemId));
        }

        function poNumKey(e, input, row){
            if(e.key === "ArrowDown"){
                e.preventDefault(); if(acMode !== "ponum" || acItems.length == 0) showPONumAC(input, row);
                acMove(1, function(o){ return "<b>PO: "+o.PO_NUM+"</b> | Price: "+o.POD_PRICE+" | O/S: "+o.OS_QTY; }, pickPONum); return false;
            }
            if(e.key === "ArrowUp"){
                e.preventDefault(); if(acMode !== "ponum" || acItems.length == 0) showPONumAC(input, row);
                acMove(-1, function(o){ return "<b>PO: "+o.PO_NUM+"</b> | Price: "+o.POD_PRICE+" | O/S: "+o.OS_QTY; }, pickPONum); return false;
            }
            if(e.key === "Enter"){
                if(acMode === "ponum" && acItems.length > 0){ e.preventDefault(); acEnter(pickPONum); return false; }
                e.preventDefault(); return false;
            }
            if(e.key === "Escape") hideAC();
        }

        function pickPONum(o){
            var row = acRow;
            setValue("po_id_"+row, o.PO_ID);
            setValue("po_num_"+row, o.PO_NUM);
            setValue("pod_price_"+row, o.POD_PRICE);
            hideAC();
            var q = byId("rcvd_qty_"+row); if(q){ q.focus(); q.select(); }
        }

        function addRow(){
            var tbody=byId("detailBody");var row=rowSeq;rowSeq++;
            var tr=document.createElement("tr");
            tr.onclick=function(){selectDetailRow(this);};
            tr.innerHTML=
                '<td class="text-center align-middle row-no" style="width:25px;"></td>' +
                '<td style="width:130px;">' +
                    '<input type="hidden" name="item_id[]" id="item_id_'+row+'">' +
                    '<input type="hidden" name="po_id[]" id="po_id_'+row+'">' +
                    '<input type="text" name="item_code[]" id="item_code_'+row+'" autocomplete="off" oninput="showPOItemAC(this, '+row+')" onkeydown="poItemKey(event, this, '+row+')">' +
                '</td>' +
                '<td><input type="text" name="item_name[]" id="item_name_'+row+'" readonly></td>' +
                '<td style="width:70px;"><input type="text" class="text-right" name="rcvd_qty[]" id="rcvd_qty_'+row+'" onkeydown="fieldEnterSave(event, this)"></td>' +
                '<td style="width:80px;"><input type="text" class="text-right" name="pod_price[]" id="pod_price_'+row+'"></td>' +
                '<td style="width:160px;"><input type="text" name="po_num[]" id="po_num_'+row+'" autocomplete="off" oninput="showPONumAC(this, '+row+')" onkeydown="poNumKey(event, this, '+row+')"></td>' +
                '<td class="text-center" style="width:40px; background:#f0f8ff;">' +
                    '<input type="checkbox" name="move_check[]" value="" onclick="event.stopPropagation();">' +
                '</td>' +
                '<td class="text-center" style="width:40px;"><button type="button" class="btn-classic" style="padding:0 5px;" onclick="deleteRow(this); event.stopPropagation();" title="Delete">X</button></td>';

            tbody.appendChild(tr);
            renumberRows();selectDetailRow(tr);byId("item_code_"+row).focus();
        }

        function deleteRow(btn){
            var tr=btn.parentNode ? btn.parentNode.parentNode : null;
            if(!confirm("Hapus baris detail ini?"))return;
            if(tr) tr.parentNode.removeChild(tr);
            renumberRows();
            var id=byId("rcv_id").value;if(id!=""&&id!="0")submitSave(true);
        }
        function renumberRows(){
            var rows=byId("detailBody").getElementsByTagName("tr");
            for(var i=0;i<rows.length;i++){
                var c=rows[i].getElementsByClassName("row-no")[0];
                if(c) {
                    c.innerHTML = (i==0) ? "<i class='fas fa-caret-right'></i>" : "";
                }
            }
        }

        // Trigger & Action Custom Buttons
        function triggerAction(actionName) {
            if(!confirm("Jalankan aksi " + actionName.toUpperCase() + "?")) return;
            byId("exec_action").value = actionName;
            byId("execForm").submit();
        }

        function triggerUpdatePrice() {
            var rcvId = byId("rcv_id").value;
            if(!rcvId || rcvId == "0") { alert("Pilih / simpan Receive terlebih dahulu."); return; }
            
            var tr = selectedDetailRow;
            if(!tr){
                var rows=byId("detailBody").getElementsByTagName("tr");
                if(rows.length>0) tr=rows[0];
            }
            if(!tr) { alert("Pilih baris detail item dulu."); return; }
            
            var itemInput = tr.querySelector('input[name="item_id[]"]');
            var poInput = tr.querySelector('input[name="po_id[]"]');
            var itemId = itemInput ? itemInput.value : "";
            var poId = poInput ? poInput.value : "";
            
            if(!itemId || !poId) { alert("Pilih detail item yang valid."); return; }
            
            if(confirm("Jalankan Update Price untuk item yang dipilih?")) {
                byId("exec_action").value = "update_price";
                byId("upd_rcv_id").value = rcvId;
                byId("upd_item_id").value = itemId;
                byId("upd_po_id").value = poId;
                byId("execForm").submit();
            }
        }

        function triggerMoveDetail() {
            var srcId = byId("rcv_id").value;
            if (!srcId || srcId == "0") { alert("Harap simpan/pilih Receive (ICL) saat ini terlebih dahulu."); return; }
            var tgtId = byId("target_rcv_id").value;
            if (!tgtId || tgtId == "0") { alert("Pilih ICL Tujuan yang valid."); return; }
            
            var checkboxes = document.querySelectorAll('input[name="move_check[]"]:checked');
            if (checkboxes.length == 0) { alert("Centang minimal 1 detail yang akan dipindah."); return; }
            
            if (confirm("Yakin pindahkan " + checkboxes.length + " item yang dipilih ke ICL: " + byId("target_rcv_no").value + "?")) {
                var form = byId("rcvForm");
                form.querySelector('input[name="action"]').value = "move_detail";
                form.submit();
            }
        }

        function triggerMoveToNew() {
            var srcId = byId("rcv_id").value;
            if (!srcId || srcId == "0") { alert("Harap simpan/pilih Receive (ICL) saat ini terlebih dahulu."); return; }
            
            var checkboxes = document.querySelectorAll('input[name="move_check[]"]:checked');
            if (checkboxes.length == 0) { alert("Centang minimal 1 baris detail di kolom 'Pindah'."); return; }
            
            if (confirm("Sistem akan otomatis:\n1. Membuat dokumen ICL BARU\n2. Memindahkan " + checkboxes.length + " item yang dicentang ke dalamnya.\n\nLanjutkan?")) {
                var form = byId("rcvForm");
                form.querySelector('input[name="action"]').value = "move_to_new";
                form.submit();
            }
        }

        function cetakICL() {
            var rcvId = byId("rcv_id").value;
            if (!rcvId || rcvId == "0") { alert("Simpan atau pilih data Receive terlebih dahulu!"); return; }
            window.open("print_icl.php?id=" + encodeURIComponent(rcvId), "CETAK_ICL", "width=900,height=700,scrollbars=yes");
        }
        function cetakICLOto() {
            var rcvId = byId("rcv_id").value;
            if (!rcvId || rcvId == "0") { alert("Simpan atau pilih data Receive terlebih dahulu!"); return; }
            window.open("print_icl_oto.php?id=" + encodeURIComponent(rcvId), "CETAK_OTO", "width=900,height=700,scrollbars=yes");
        }
        function cetakSchedule() {
            var rcvNo = document.querySelector('.rcv-no').value;
            if (!rcvNo) { alert("Simpan atau pilih data Receive terlebih dahulu!"); return; }
            var shortNo = rcvNo.substring(0, 6);
            window.open("print_schedule.php?no=" + encodeURIComponent(shortNo), "CETAK_SCH", "width=900,height=700,scrollbars=yes");
        }
        function setNextICL() {
            var nextNum = prompt("Masukkan nilai antrian untuk ICL selanjutnya:", "");
            if (nextNum !== null && nextNum.trim() !== "") {
                if (isNaN(nextNum)) { alert("Format harus berupa angka!"); return; }
                byId("exec_action").value = "set_next_icl";
                byId("upd_rcv_id").value = nextNum; 
                byId("execForm").submit();
            }
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

        ready(function(){
            var rows=document.getElementById("detailBody").getElementsByTagName("tr");
            for(var i=0;i<rows.length;i++){
                rows[i].onclick=function(){selectDetailRow(this);};
            }
            renumberRows();
        });
        
        if(document.addEventListener) {
            document.addEventListener("click", function(e){
                initAC();if(acBox&&!acBox.contains(e.target)){if(!e.target||!e.target.getAttribute||e.target.getAttribute("autocomplete")!=="off")hideAC();}
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
    <div class="title-bar">
        Receive
    </div>

    <?php if ($message != "") { echo "<div style='background:#d4edda; color:#155724; padding:5px; text-align:center; font-size:12px;'>$message</div>"; } ?>
    <?php if ($error != "") { echo "<div style='background:#f8d7da; color:#721c24; padding:5px; text-align:center; font-size:12px;'>$error</div>"; } ?>

    <form id="rcvForm" method="post" action="receive.php" style="display: flex; flex-direction: column; flex-grow: 1;">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="rcv_id" id="rcv_id" value="<?php echo h($header["RCV_ID"]); ?>">
        <input type="hidden" name="sup_id" id="sup_id" value="<?php echo h($header["SUP_ID"]); ?>">
        <input type="hidden" name="allow_empty_detail" id="allow_empty_detail" value="0">

        <!-- HEADER TEAL AREA -->
        <div class="teal-header">
            <div class="row">
                <!-- Kiri: Data Utama -->
                <div class="col-8">
                    <div class="row">
                        <div class="col-3">
                            <label class="lbl-yellow">I.C.L No.</label>
                            <input type="text" class="input-classic rcv-no" name="rcv_no" value="<?php echo h($header["RCV_NO"]); ?>">
                        </div>
                        <div class="col-3">
                            <label class="lbl-yellow">Date</label>
                            <input type="date" class="input-classic" name="rcv_date" value="<?php echo h($header["RCV_DATE"]); ?>">
                        </div>
                        <div class="col-6">
                            <label class="lbl-yellow">D.O #</label>
                            <input type="text" class="input-classic" name="rcv_dono" value="<?php echo h($header["RCV_DONO"]); ?>">
                        </div>
                    </div>
                    
                    <div class="row mt-1">
                        <div class="col-3">
                            <label class="lbl-yellow">Sup.Code</label>
                            <input type="text" class="input-classic" name="sup_code" id="sup_code" value="<?php echo h($header["SUP_CODE"]); ?>" autocomplete="off" oninput="showSupplierAC(this)" onkeydown="supplierKey(event, this)">
                        </div>
                        <div class="col-9">
                            <label class="lbl-yellow">Sup.Company</label>
                            <input type="text" class="input-classic bg-light" name="sup_comp" id="sup_comp" value="<?php echo h($header["SUP_COMP"]); ?>" readonly>
                        </div>
                    </div>

                    <div class="row mt-1">
                        <div class="col-3">
                            <label class="lbl-yellow">PIC</label>
                            <input type="text" class="input-classic" name="rcv_pic" id="rcv_pic" value="<?php echo h($header["RCV_PIC"]); ?>" autocomplete="off" oninput="showPicAC(this)" onkeydown="picKey(event, this)">
                        </div>
                        <div class="col-3">
                            <label class="lbl-yellow">TYPE</label>
                            <select class="input-classic" name="rcv_type">
                                <option value="1" <?php echo $header["RCV_TYPE"]=="1"?"selected":""; ?>>Material - Supplier</option>
                                <option value="2" <?php echo $header["RCV_TYPE"]=="2"?"selected":""; ?>>Spare Part - Supplier</option>
                                <option value="3" <?php echo $header["RCV_TYPE"]=="3"?"selected":""; ?>>Part - Vendor</option>
                                <option value="4" <?php echo $header["RCV_TYPE"]=="4"?"selected":""; ?>>Others</option>
                            </select>
                        </div>
                        <div class="col-2">
                            <label class="lbl-yellow">Jenis BC</label>
                            <select class="input-classic" name="jenis_bc">
                                <option value=""></option>
                                <?php foreach($bcList as $b) { ?>
                                    <option value="<?php echo h($b); ?>" <?php echo $header["JENIS_BC"]==$b?"selected":""; ?>><?php echo h($b); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-2">
                            <label class="lbl-yellow">No BC</label>
                            <input type="text" class="input-classic" name="nomor_bc" value="<?php echo h($header["NOMOR_BC"]); ?>">
                        </div>
                        <div class="col-2">
                            <label class="lbl-yellow">Tgl BC</label>
                            <input type="date" class="input-classic" name="bc_date" value="<?php echo h($header["BC_DATE"]); ?>">
                        </div>
                    </div>

                    <div class="row mt-2">
                        <div class="col-12">
                            <fieldset class="fieldset-control">
                                <legend>Control</legend>
                                <button type="button" class="btn-classic btn-action-wide" onclick="newData()">Tambah</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="goEdit('<?php echo h($editId); ?>')">Rubah</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="deleteCurrent()">Hapus</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="submitSave(false)">Simpan</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="newData()">Batal</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="window.location.href='receive.php'">Refresh</button>
                            </fieldset>
                        </div>
                    </div>
                </div>

                <!-- Kanan: Tombol Cetak & Search Data -->
                <div class="col-4">
                    <div style="display:flex; flex-direction:column; align-items:flex-end;">
                        <!-- Pencarian Data -->
                        <div style="margin-bottom: 20px; width: 100%;">
                            <label class="lbl-white text-right">SEARCH DATA</label>
                            <input type="text" name="q" class="input-classic" style="width:100%;" value="<?php echo h($q); ?>" placeholder="Search RCV No / DO / Sup" autocomplete="off" oninput="showRcvAC(this)" onkeydown="rcvKey(event, this)">
                        </div>
                        
                        <div style="display:flex; flex-wrap:wrap; justify-content:flex-end;">
                            <button type="button" class="btn-classic btn-action-side mr-1" onclick="cetakICLOto()">ICL OTO</button>
                           
                        </div>
                        <div style="display:flex; flex-wrap:wrap; justify-content:flex-end;">
                            
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
                        <th style="width:130px;">ITEM_CODE</th>
                        <th>ITEM_NAME</th>
                        <th style="width:70px;" class="text-right">RCVD_QTY</th>
                        <th style="width:80px;" class="text-right">POD_PRICE</th>
                        <th style="width:160px;">PO_NUM</th>
                        <th style="width:40px;" class="text-center" title="Pindah ICL">Pindah</th>
                        <th style="width:40px;" class="text-center">ACT</th>
                    </tr>
                </thead>
                <tbody id="detailBody">
                    <?php for($i=0;$i<count($details);$i++){ 
                        $d=$details[$i];
                        $qty=isset($d["RCVD_QTY"])?floatval($d["RCVD_QTY"]):0;
                        $price=isset($d["POD_PRICE"])?floatval($d["POD_PRICE"]):0;
                    ?>
                        <tr>
                            <td class="text-center align-middle row-no" style="width:25px;"></td>
                            <td style="width:130px;">
                                <input type="hidden" name="item_id[]" id="item_id_<?php echo h($i); ?>" value="<?php echo h($d["ITEM_ID"]); ?>">
                                <input type="hidden" name="po_id[]" id="po_id_<?php echo h($i); ?>" value="<?php echo h($d["PO_ID"]); ?>">
                                <input type="text" name="item_code[]" id="item_code_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_CODE"])?$d["ITEM_CODE"]:""); ?>" autocomplete="off" oninput="showPOItemAC(this, <?php echo h($i); ?>)" onkeydown="poItemKey(event, this, <?php echo h($i); ?>)">
                            </td>
                            <td><input type="text" name="item_name[]" id="item_name_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_NAME"])?$d["ITEM_NAME"]:""); ?>" readonly></td>
                            
                            <!-- Memanggil fieldEnterSave dengan 'this' -->
                            <td style="width:70px;"><input type="text" class="text-right" name="rcvd_qty[]" id="rcvd_qty_<?php echo h($i); ?>" value="<?php echo h($qty==0?"":$qty); ?>" onkeydown="fieldEnterSave(event, this)"></td>
                            
                            <td style="width:80px;"><input type="text" class="text-right" name="pod_price[]" id="pod_price_<?php echo h($i); ?>" value="<?php echo h($price==0?"":$price); ?>"></td>
                            <td style="width:160px;"><input type="text" name="po_num[]" id="po_num_<?php echo h($i); ?>" value="<?php echo h(isset($d["PO_NUM"])?$d["PO_NUM"]:""); ?>" autocomplete="off" oninput="showPONumAC(this, <?php echo h($i); ?>)" onkeydown="poNumKey(event, this, <?php echo h($i); ?>)"></td>
                            <td class="text-center" style="width:40px; background:#f0f8ff;">
                                <?php if (!empty($d["ITEM_ID"]) && !empty($d["PO_ID"])) { ?>
                                    <input type="checkbox" name="move_check[]" value="<?php echo h($d["ITEM_ID"].'|'.$d["PO_ID"]); ?>" onclick="event.stopPropagation();">
                                <?php } ?>
                            </td>
                            <td class="text-center" style="width:40px;"><button type="button" class="btn-classic" style="padding:0 5px;" onclick="deleteRow(this); event.stopPropagation();" title="Delete">X</button></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- FITUR PANEL PINDAH ICL -->
        <?php if ($editId > 0) { ?>
        <div style="background: #e0f7fa; padding: 6px 10px; border-top: 1px solid #b2ebf2; display: flex; align-items: center; gap: 8px;">
            <strong style="font-size:11px; color:#006064;">Pindah Item Diconteng:</strong>
            <button type="button" class="btn-classic" style="background:#b2dfdb;" onclick="triggerMoveToNew()">Pindah ke ICL Baru</button>
            <span>atau</span>
            <input type="hidden" name="target_rcv_id" id="target_rcv_id" value="">
            <input type="text" class="input-classic" style="width: 180px;" name="target_rcv_no" id="target_rcv_no" placeholder="Ketik No ICL Tujuan..." autocomplete="off" oninput="showTargetRcvAC(this)" onkeydown="targetRcvKey(event, this)">
            <button type="button" class="btn-classic" onclick="triggerMoveDetail()">Pindah ke ICL Ini</button>
        </div>
        <?php } ?>

        <!-- FOOTER BAR -->
        <div class="footer-bar">
            <div>
                <button type="button" class="btn-classic" onclick="addRow()"><i class="fas fa-plus"></i> Tambah Baris</button>
            </div>
            <div style="text-align: right;">
                <button type="button" class="btn-classic" onclick="triggerAction('trigger_off')">OFF</button>
                <button type="button" class="btn-classic" onclick="triggerUpdatePrice()">UPDATE</button>
                <button type="button" class="btn-classic" onclick="triggerAction('trigger_on')">ON</button>
                <br>
                <span style="font-weight: bold; font-size: 11px; color: #000; margin-top:2px; display:inline-block;">
                    JANGAN LUPA KLIK TOMBOL ON LAGI SETELAH KLIK TOMBOL OFF
                </span>
            </div>
        </div>
    </form>

    <form id="deleteForm" method="post" action="receive.php" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="rcv_id" id="delete_rcv_id" value="">
    </form>

    <form id="execForm" method="post" action="receive.php" style="display:none;">
        <input type="hidden" name="action" id="exec_action" value="">
        <input type="hidden" name="upd_rcv_id" id="upd_rcv_id" value="">
        <input type="hidden" name="upd_item_id" id="upd_item_id" value="">
        <input type="hidden" name="upd_po_id" id="upd_po_id" value="">
        <input type="hidden" name="edit" value="<?php echo h($editId); ?>">
    </form>
</div>

<div id="acBox" class="ac-box"></div>

</body>
</html>