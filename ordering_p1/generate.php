<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    header("Location: login.php?error=session_expired");
    exit();
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

 $today = date('Y-m-d');
 $firstDayOfMonth = date('Y-m-01');
 $dbUser = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : 'System';

 $msgGenerate = "";
 $statGenerate = "";
 $msgTransfer = "";
 $statTransfer = "";

// =========================================================================
// 1. FUNGSI LOGIKA PENOMORAN (GENERATE)
// =========================================================================

function GetNextDINo($conn, $UseDate, $offset = 0) {
    $ts = strtotime($UseDate);
    if ($ts === false) $ts = time();
    $yymm = date('y', $ts) . date('m', $ts); 
    
    $minNo = intval($yymm . "001");  
    $maxNo = intval($yymm . "999");  

    $sql = "
        SELECT MAX(CAST(DI_NO AS INT)) AS MaxNo FROM (
            SELECT DI_NO FROM dbo.DI WHERE DI_NO BETWEEN ? AND ?
            UNION
            SELECT DI_NO FROM dbo.DI_TEMP WHERE DI_NO BETWEEN ? AND ?
        ) t
    ";
    $stmt = sqlsrv_query($conn, $sql, array($minNo, $maxNo, $minNo, $maxNo));
    
    $lastNo = 0;
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $lastNo = intval($row['MaxNo']);
    }

    if ($lastNo == 0) {
        return $minNo + $offset; 
    } else {
        return $lastNo + 1 + $offset; 
    }
}

function GenerateDSNo_275($conn, $UseDate, $offset = 0) {
    $ts = strtotime($UseDate);
    if ($ts === false) $ts = time();
    $monthStr = date('m', $ts);
    $yearStr  = date('y', $ts);

    $sql = "
        SELECT TOP 1 DI_DSNO FROM (
            SELECT DI_DSNO, DI_ID FROM dbo.DI WHERE CUST_ID = 275 AND DI_DSNO IS NOT NULL
            UNION
            SELECT DI_DSNO, DI_ID FROM dbo.DI_TEMP WHERE CUST_ID = 275 AND DI_DSNO IS NOT NULL
        ) t ORDER BY DI_ID DESC
    ";
    $stmt = sqlsrv_query($conn, $sql);
    
    $lastNo = 0;
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $lastDS = trim($row['DI_DSNO']);
        $lastMonth = substr($lastDS, 6, 2);
        $lastYear  = substr($lastDS, 9, 2);

        if ($lastMonth == $monthStr && $lastYear == $yearStr) {
            $parts = explode('/', $lastDS);
            $lastNo = intval($parts[0]);
        }
    }
    $newNo = $lastNo + 1 + $offset;
    return sprintf('%03d/E/%s/%s', $newNo, $monthStr, $yearStr);
}

function GenerateDSNo_Other($conn, $CustAbbr, $UseDate, $offset = 0) {
    $rom = array('I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII');
    $ts = strtotime($UseDate);
    if ($ts === false) $ts = time();
    $m = intval(date('m', $ts));
    $y = date('Y', $ts);

    $monthRoman = $rom[$m - 1];
    $year2 = substr($y, 2, 2);
    $pattern = '%/IMC/DS/' . trim($CustAbbr) . '/%/' . $year2;

    $sql = "
        SELECT TOP 1 DI_DSNO FROM (
            SELECT DI_DSNO, DI_ID FROM dbo.DI WHERE DI_DSNO LIKE ?
            UNION
            SELECT DI_DSNO, DI_ID FROM dbo.DI_TEMP WHERE DI_DSNO LIKE ?
        ) t ORDER BY DI_ID DESC
    ";
    $stmt = sqlsrv_query($conn, $sql, array($pattern, $pattern));
    
    $newNo = 1;
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $lastDS = trim($row['DI_DSNO']);
        $pPos = strpos($lastDS, '/IMC/');
        if ($pPos !== false && $pPos > 0) {
            $lastNo = intval(substr($lastDS, 0, $pPos));
            if ($lastNo > 0) {
                $newNo = $lastNo + 1;
            }
        }
    }

    $newNo = $newNo + $offset; 
    $dsno = sprintf('%03d/IMC/DS/%s/%s/%s', $newNo, trim($CustAbbr), $monthRoman, $year2);
    return str_replace('/DS/', '/INV/', $dsno);
}

// =========================================================================
// 2. PROSES POST (GENERATE & TRANSFER)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['ACTION_TYPE']) ? trim($_POST['ACTION_TYPE']) : '';

    // ---------------------------------------------------------
    // A. LOGIKA GENERATE & CLEAR SEMENTARA
    // ---------------------------------------------------------
    if ($action === 'generate' || $action === 'clear_only') {
        $cust_code    = isset($_POST['CUST_CODE']) ? trim($_POST['CUST_CODE']) : '';
        $start_raw    = isset($_POST['START_DATE']) ? trim($_POST['START_DATE']) : $firstDayOfMonth;
        $end_raw      = isset($_POST['DI_DATE']) ? trim($_POST['DI_DATE']) : $today;
        $order_no     = isset($_POST['DI_ORDERNO']) ? trim($_POST['DI_ORDERNO']) : '';
        $force_append = isset($_POST['FORCE_APPEND']) ? true : false; 
        $clear_pwd    = isset($_POST['CLEAR_PASSWORD']) ? trim($_POST['CLEAR_PASSWORD']) : ''; 

        $start_date = date('Ymd', strtotime($start_raw));
        $end_date   = date('Ymd', strtotime($end_raw));

        if ($action === 'clear_only') {
            if ($clear_pwd === 'q9tj9') {
                sqlsrv_begin_transaction($conn);
                try {
                    sqlsrv_query($conn, "DELETE FROM dbo.DI_PART_TEMP");
                    sqlsrv_query($conn, "DELETE FROM dbo.DI_TEMP");
                    sqlsrv_commit($conn);
                    $msgGenerate = "Data temporary berhasil dikosongkan sepenuhnya.";
                    $statGenerate = "success";
                } catch (Exception $e) {
                    sqlsrv_rollback($conn);
                    $msgGenerate = "Gagal mengosongkan data: " . $e->getMessage();
                    $statGenerate = "error";
                }
            } else {
                $msgGenerate = "Gagal mengosongkan data: Password salah!";
                $statGenerate = "error";
            }
        } 
        else {
            $customers_to_process = array();
            if ($cust_code == '') {
                $sql_all_cust = "SELECT CUST_CODE, CUST_ID, CUST_ABBR FROM dbo.CUST";
                $stmt_all = sqlsrv_query($conn, $sql_all_cust);
                if ($stmt_all !== false) {
                    while ($c_row = sqlsrv_fetch_array($stmt_all, SQLSRV_FETCH_ASSOC)) {
                        $customers_to_process[] = $c_row;
                    }
                }
            } else {
                $sql_cust = "SELECT TOP 1 CUST_ID, CUST_ABBR, CUST_CODE FROM dbo.CUST WHERE CUST_CODE = ?";
                $stmt_cust = sqlsrv_query($conn, $sql_cust, array($cust_code));
                if ($stmt_cust !== false && $row_c = sqlsrv_fetch_array($stmt_cust, SQLSRV_FETCH_ASSOC)) {
                    $customers_to_process[] = $row_c;
                }
            }

            if (empty($customers_to_process)) {
                $msgGenerate = "Tidak ada customer yang ditemukan!";
                $statGenerate = "error";
            } else {
                $success_count = 0;
                $skipped_count = 0;
                $error_messages = array();

                foreach ($customers_to_process as $cust) {
                    $c_id   = intval($cust['CUST_ID']);
                    $c_code = trim($cust['CUST_CODE']);
                    $c_abbr = trim($cust['CUST_ABBR']);

                    $sql_chk = "SET NOCOUNT ON; EXEC dbo.SP_DELIVERY_INSTRUCTION_PO1 ?, ?, ?";
                    $stmt_chk = sqlsrv_query($conn, $sql_chk, array($c_code, $start_date, $end_date));
                    
                    if ($stmt_chk === false) continue; 

                    $sp_rows = array();
                    while ($s_row = sqlsrv_fetch_array($stmt_chk, SQLSRV_FETCH_ASSOC)) {
                        $sp_rows[] = $s_row;
                    }
                    if (empty($sp_rows)) continue; 

                    $grouped_rows = array();
                    foreach ($sp_rows as $sp_row) {
                        $loc = isset($sp_row['LOCATION']) ? trim($sp_row['LOCATION']) : '';
                        $group_key = ($c_id == 275) ? ($loc == '' ? 'BLANK' : $loc) : 'ALL';
                        if (!isset($grouped_rows[$group_key])) {
                            $grouped_rows[$group_key] = array();
                        }
                        $grouped_rows[$group_key][] = $sp_row;
                    }

                    sqlsrv_begin_transaction($conn);
                    try {
                        $offset = 0; 
                        foreach ($grouped_rows as $group_key => $group_items) {
                            $header_order_no = $order_no;
                            if ($header_order_no === '' && isset($group_items[0]['PO'])) {
                                $header_order_no = trim($group_items[0]['PO']);
                            }

                            if (!$force_append) {
                                $sql_cek_exist = "
                                    SELECT TOP 1 DI_ID FROM dbo.DI_TEMP 
                                    WHERE CUST_CODE = ? AND DI_START_DATE = ? AND DI_DATE = ? AND ISNULL(DI_ORDERNO, '') = ?
                                ";
                                $stmt_cek = sqlsrv_query($conn, $sql_cek_exist, array($c_code, $start_raw, $end_raw, $header_order_no));
                                if ($stmt_cek !== false && sqlsrv_has_rows($stmt_cek)) {
                                    $skipped_count++;
                                    continue; 
                                }
                            }

                            $di_no = GetNextDINo($conn, $end_raw, $offset);
                            if ($c_id == 275) {
                                $di_dsno = GenerateDSNo_275($conn, $end_raw, $offset);
                                $di_invno = $di_dsno; 
                            } else {
                                if ($c_abbr == '') throw new Exception("Customer Abbr kosong.");
                                $di_dsno = GenerateDSNo_Other($conn, $c_abbr, $end_raw, $offset);
                                $di_invno = str_replace('/DS/', '/INV/', $di_dsno); 
                            }

                            $sql_ins_di = "
                                INSERT INTO dbo.DI_TEMP (DI_NO, CUST_ID, CUST_CODE, DI_START_DATE, DI_DATE, DI_INVNO, DI_DSNO, DI_ORDERNO, DI_POSTED) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0);
                                SELECT SCOPE_IDENTITY() AS NEW_DI_ID;
                            ";
                            $stmt_di = sqlsrv_query($conn, $sql_ins_di, array($di_no, $c_id, $c_code, $start_raw, $end_raw, $di_invno, $di_dsno, $header_order_no));
                            if ($stmt_di === false) throw new Exception("Gagal insert header.");

                            sqlsrv_next_result($stmt_di);
                            $row_di_id = sqlsrv_fetch_array($stmt_di, SQLSRV_FETCH_ASSOC);
                            $new_di_id = $row_di_id ? intval($row_di_id['NEW_DI_ID']) : 0;
                            if ($new_di_id <= 0) throw new Exception("Gagal mendapatkan DI_ID temporary.");
                            
                            $lino = 1;
                            foreach ($group_items as $sp_row) {
                                $part_num  = isset($sp_row['PART_NUM']) ? trim($sp_row['PART_NUM']) : '';
                                $part_no   = isset($sp_row['PART_NO']) ? trim($sp_row['PART_NO']) : '';
                                $part_name = isset($sp_row['PART_NAME']) ? trim($sp_row['PART_NAME']) : '';
                                $plan_qty  = isset($sp_row['PLAN_QTY']) ? floatval($sp_row['PLAN_QTY']) : 0;
                                $pb_qty    = isset($sp_row['PBQTY']) ? floatval($sp_row['PBQTY']) : 0;
                                
                                if ($plan_qty > 0 && $pb_qty > 0) $qty_to_load = ($plan_qty < $pb_qty) ? $plan_qty : $pb_qty;
                                else if ($plan_qty > 0) $qty_to_load = $plan_qty;
                                else $qty_to_load = $pb_qty;

                                // PERBAIKAN: Ambil nilai DIPA_PQTY dari hasil SP (Bisa STD_BOX atau STD_PACK_BOX)
                                $dipa_pqty = isset($sp_row['STD_BOX']) ? intval($sp_row['STD_BOX']) : (isset($sp_row['STD_PACK_BOX']) ? intval($sp_row['STD_PACK_BOX']) : 0);
                                
                                $location  = isset($sp_row['LOCATION']) ? trim($sp_row['LOCATION']) : '';
                                if ($c_id != 275) $location = '';

                                $part_id = 0; $price_id = 0; $part_code_from_view = '';
                                $pack_id = 1; // Default
                                $dipa_pack = '';

                                if ($part_num != '') {
                                    // PERBAIKAN: Join ke tabel STD_PACK dan PACK untuk mencari PACK_ID dan PACK_CODE
                                    $sql_part_info = "
                                        SELECT TOP 1 
                                            PV.PART_ID, 
                                            PV.PRICE_ID, 
                                            PV.PART_CODE,
                                            SP.PACK_ID,
                                            P.PACK_CODE
                                        FROM dbo.PART_VIEW PV
                                        LEFT JOIN dbo.STD_PACK SP ON PV.PART_CODE = SP.ITEM_CODE
                                        LEFT JOIN dbo.PACK P ON SP.PACK_ID = P.PACK_ID
                                        WHERE PV.PART_NUM = ?
                                    ";
                                    $stmt_pi = sqlsrv_query($conn, $sql_part_info, array($part_num));
                                    if ($stmt_pi !== false && $pi_row = sqlsrv_fetch_array($stmt_pi, SQLSRV_FETCH_ASSOC)) {
                                        $part_id  = intval($pi_row['PART_ID']);
                                        $price_id = intval($pi_row['PRICE_ID']);
                                        $part_code_from_view = trim($pi_row['PART_CODE']);
                                        
                                        // Set PACK_ID dan DIPA_PACK (PACK_CODE) jika ditemukan
                                        if (!empty($pi_row['PACK_ID'])) {
                                            $pack_id = intval($pi_row['PACK_ID']);
                                        }
                                        if (!empty($pi_row['PACK_CODE'])) {
                                            $dipa_pack = trim($pi_row['PACK_CODE']);
                                        }
                                    }
                                }

                                if ($qty_to_load > 0 && $part_num != '' && $part_no != '' && $part_name != '' && $price_id > 0) {
                                    $part_code_final = ($part_code_from_view != '') ? $part_code_from_view : substr($part_num, 0, 8);
                                    $part_code_8 = substr($part_code_final, 0, 8);

                                    // PERBAIKAN: Mengganti hardcode '1' di PACK_ID menjadi parameter '?'
                                    $sql_ins_part = "
                                        INSERT INTO dbo.DI_PART_TEMP 
                                        (DI_ID, DIPA_LINO, PART_CODE, PART_ID, DIPA_QTY, PACK_ID, DIPA_PACK, DIPA_PQTY, BDQTY, PRICE_ID, LOCATION, DIPA_POSTED, IS_MANUAL)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)
                                    ";
                                    sqlsrv_query($conn, $sql_ins_part, array(
                                        $new_di_id, 
                                        $lino, 
                                        $part_code_8, 
                                        $part_id, 
                                        $qty_to_load, 
                                        $pack_id,                     // Didapat dari STD_PACK
                                        substr($dipa_pack, 0, 10),    // Didapat dari PACK.PACK_CODE
                                        $dipa_pqty,                   // Didapat dari SP.STD_BOX
                                        $pb_qty, 
                                        $price_id, 
                                        substr($location, 0, 30)
                                    ));
                                    $lino++;
                                }
                            }
                            $offset++;
                        } 
                        sqlsrv_commit($conn);
                        $success_count++;
                    } catch (Exception $e) {
                        sqlsrv_rollback($conn);
                        $error_messages[] = "Cust " . $c_code . ": " . $e->getMessage();
                    }
                }

                if ($success_count > 0 || $skipped_count > 0) {
                    $msgGenerate = "SELESAI! Berhasil generate <strong>$success_count</strong> data baru.";
                    if ($skipped_count > 0 && !$force_append) $msgGenerate .= " (<strong>$skipped_count</strong> dilewati karena sudah ada).";
                    if (!empty($error_messages)) $msgGenerate .= "<br>Error: " . implode(", ", $error_messages);
                    $statGenerate = "success";
                } else {
                    $msgGenerate = "Tidak ada data baru yang digenerate.";
                    $statGenerate = "error";
                }
            }
        }
    }

    // ---------------------------------------------------------
    // B. LOGIKA TRANSFER DENGAN BYPASS TRIGGER FIFO
    // ---------------------------------------------------------
    if ($action === 'transfer_selected' || $action === 'transfer_all') {
        $selected_ids = isset($_POST['DI_IDS']) ? $_POST['DI_IDS'] : array();

        if ($action === 'transfer_all') {
            $selected_ids = array();
            $sql_all = "SELECT DI_ID FROM dbo.DI_TEMP";
            $stmt_all = sqlsrv_query($conn, $sql_all);
            if ($stmt_all !== false) {
                while ($row = sqlsrv_fetch_array($stmt_all, SQLSRV_FETCH_ASSOC)) {
                    $selected_ids[] = $row['DI_ID'];
                }
            }
        }

        if (empty($selected_ids)) {
            $msgTransfer = "Tidak ada data yang dipilih untuk ditransfer.";
            $statTransfer = "error";
        } else {
            $success_count = 0;
            $error_messages = array();

            // ==========================================================
            // MENGAKTIFKAN SAKLAR BYPASS TRIGGER FIFO (REPOSTING MODE)
            // ==========================================================
            sqlsrv_query($conn, "UPDATE dbo.CONTROL_FLAGS SET FLAG_VALUE = 1 WHERE FLAG_NAME = 'REPOSTING_MODE'");

            foreach ($selected_ids as $temp_di_id) {
                sqlsrv_begin_transaction($conn);
                try {
                    // PERHATIAN: Kolom DI_ORDERNO Dihapus dari Query INSERT untuk menghindari Error Truncated
                    $sql_ins_di = "
                        SET NOCOUNT ON;
                        INSERT INTO dbo.DI (
                            DI_NO, CUST_ID, CUST_CODE, DI_START_DATE, DI_DATE, DI_INVNO, DI_DSNO, DI_BCNO, DI_DSRET, DI_DSRETDATE,
                            DI_INVRET, DI_INVRETDATE, DI_BCRET, DI_BCRETDATE, DI_BCDEST, DI_POSTED, DATE_CREATED, DATE_UPDATED, TRAN_ID
                        )
                        SELECT 
                            DI_NO, CUST_ID, CUST_CODE, DI_START_DATE, DI_DATE, DI_INVNO, DI_DSNO, DI_BCNO, DI_DSRET, DI_DSRETDATE,
                            DI_INVRET, DI_INVRETDATE, DI_BCRET, DI_BCRETDATE, DI_BCDEST, DI_POSTED, DATE_CREATED, DATE_UPDATED, TRAN_ID
                        FROM dbo.DI_TEMP WHERE DI_ID = ?;
                        
                        SELECT SCOPE_IDENTITY() AS NEW_DI_ID;
                    ";
                    
                    $stmt_di = sqlsrv_query($conn, $sql_ins_di, array($temp_di_id));
                    if ($stmt_di === false) {
                        $errs = sqlsrv_errors();
                        $errMsg = "Gagal insert ke tabel DI.";
                        if (!empty($errs)) $errMsg .= " Info SQL: " . $errs[0]['message'];
                        throw new Exception($errMsg);
                    }

                    $new_di_id = 0;
                    do {
                        while ($row = sqlsrv_fetch_array($stmt_di, SQLSRV_FETCH_ASSOC)) {
                            if (isset($row['NEW_DI_ID'])) {
                                $new_di_id = intval($row['NEW_DI_ID']);
                            }
                        }
                    } while (sqlsrv_next_result($stmt_di));

                    if ($new_di_id <= 0) {
                        throw new Exception("Gagal mendapatkan DI_ID baru. Proses SCOPE_IDENTITY() gagal.");
                    }

                    $sql_ins_part = "
                        INSERT INTO dbo.DI_PART (
                            DI_ID, DIPA_LINO, PART_CODE, PART_ID, SPR_CODE, DIPA_QTY, PACK_ID, DIPA_PACK, DIPA_PQTY, DIPA_POSTED,
                            PRICE_ID, DIPA_CLOSE, LOCATION, IS_MANUAL, MANUAL_ORDR_ID, MANUAL_ORDP_LINO, BDQTY, BC_NO
                        )
                        SELECT 
                            " . intval($new_di_id) . ", DIPA_LINO, PART_CODE, PART_ID, SPR_CODE, DIPA_QTY, PACK_ID, DIPA_PACK, DIPA_PQTY, DIPA_POSTED,
                            PRICE_ID, DIPA_CLOSE, LOCATION, IS_MANUAL, MANUAL_ORDR_ID, MANUAL_ORDP_LINO, BDQTY, BC_NO
                        FROM dbo.DI_PART_TEMP WHERE DI_ID = ?;
                    ";
                    
                    $stmt_part = sqlsrv_query($conn, $sql_ins_part, array($temp_di_id));
                    if ($stmt_part === false) {
                        $errs = sqlsrv_errors();
                        $errMsg = "Gagal insert ke tabel DI_PART.";
                        if (!empty($errs)) $errMsg .= " Info SQL: " . $errs[0]['message'];
                        throw new Exception($errMsg);
                    }

                    // Hapus data dari Temp setelah sukses dipindah
                    sqlsrv_query($conn, "DELETE FROM dbo.DI_PART_TEMP WHERE DI_ID = ?", array($temp_di_id));
                    sqlsrv_query($conn, "DELETE FROM dbo.DI_TEMP WHERE DI_ID = ?", array($temp_di_id));

                    sqlsrv_commit($conn);
                    $success_count++;

                } catch (Exception $e) {
                    sqlsrv_rollback($conn);
                    $error_messages[] = "ID Temp " . $temp_di_id . ": " . $e->getMessage();
                }
            }

            // ==========================================================
            // MEMATIKAN KEMBALI SAKLAR BYPASS SETELAH SELESAI
            // ==========================================================
            sqlsrv_query($conn, "UPDATE dbo.CONTROL_FLAGS SET FLAG_VALUE = 0 WHERE FLAG_NAME = 'REPOSTING_MODE'");

            if ($success_count > 0) {
                $msgTransfer = "Berhasil mentransfer <strong>$success_count</strong> data ke original tanpa pemotongan stok.";
                if (!empty($error_messages)) $msgTransfer .= "<br>Error tersisa: " . implode("<br>", $error_messages);
                $statTransfer = "success";
            } else {
                $msgTransfer = "Semua data gagal ditransfer.<br>" . implode("<br>", $error_messages);
                $statTransfer = "error";
            }
        }
    }
}

// =========================================================================
// AMBIL DATA DI_TEMP UNTUK DITAMPILKAN DI TABEL KANAN
// =========================================================================
$temp_data = array();
$sql_get_temp = "
    SELECT 
        T.DI_ID, 
        T.DI_NO, 
        T.DI_INVNO,
        C.CUST_COMP, 
        (SELECT COUNT(*) FROM dbo.DI_PART_TEMP P WHERE P.DI_ID = T.DI_ID) AS TOTAL_ITEMS
    FROM dbo.DI_TEMP T
    LEFT JOIN dbo.CUST C ON T.CUST_ID = C.CUST_ID
    ORDER BY T.DI_ID ASC
";
$stmt_get = sqlsrv_query($conn, $sql_get_temp);
if ($stmt_get !== false) {
    while ($row = sqlsrv_fetch_array($stmt_get, SQLSRV_FETCH_ASSOC)) {
        $temp_data[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Auto Generate & Transfer DI</title>
    <style>
        body { margin: 0; padding: 0; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 12px; color: #000000; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { background: #000080; color: #ffffff; padding: 7px 10px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .topbar a { color: #ffffff; text-decoration: none; margin-left: 12px; }
        
        .layout-container { display: flex; flex: 1; padding: 15px; gap: 15px; box-sizing: border-box; overflow: hidden; }
        
        /* Panel Kiri & Kanan */
        .panel { background: #d4d0c8; border: 2px solid #808080; box-shadow: 2px 2px 5px #808080; padding: 15px; box-sizing: border-box; display: flex; flex-direction: column; }
        .left-panel { flex: 0 0 450px; overflow-y: auto; }
        .right-panel { flex: 1; overflow-y: auto; }

        .title { font-weight: bold; margin-bottom: 12px; font-size: 14px; border-bottom: 1px solid #808080; padding-bottom: 5px; }
        .form-grid { display: grid; grid-template-columns: 140px 1fr; gap: 8px; align-items: center; margin-bottom: 10px; }
        input[type="text"], input[type="date"], select { height: 24px; border: 1px solid #808080; background: #ffffff; font-family: Tahoma, Arial, sans-serif; font-size: 12px; padding: 2px 5px; box-sizing: border-box; width: 100%; }
        
        button { font-family: Tahoma, Arial, sans-serif; font-size: 12px; background: #d4d0c8; border: 2px outset #ffffff; padding: 6px 14px; cursor: pointer; font-weight: bold; }
        button:active { border: 2px inset #ffffff; }
        .btn-danger { background: #e0c2c2; color: #800000; }
        .btn-primary { background: #c6d8e8; color: #000080; }
        .btn-success { background: #d4edda; color: #155724; }
        
        .button-row { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; border-top: 1px solid #808080; padding-top: 10px; }
        .checkbox-container { display: flex; align-items: center; gap: 5px; font-size: 11px; margin-top: 5px; font-weight: bold; color: #000080; }
        
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 12px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 12px; }
        
        .autocomplete-wrap { position: relative; width: 100%; }
        .autocomplete-list { display: none; position: absolute; z-index: 9999; top: 24px; left: 0; right: 0; max-height: 140px; overflow-y: auto; background: #c6d8e8; border: 1px solid #808080; }
        .autocomplete-item { padding: 4px; cursor: pointer; border-bottom: 1px solid #808080; }
        .autocomplete-item:hover { background: #316ac5; color: #ffffff; }
        .note { font-size: 11px; color: #555; margin-top: 4px; line-height: 1.3; }

        /* Style Tabel */
        .toolbar { display: flex; justify-content: space-between; margin-bottom: 10px; align-items: center; }
        table { width: 100%; border-collapse: collapse; background: #ffffff; border: 1px solid #808080; }
        th, td { border: 1px solid #808080; padding: 6px 8px; text-align: left; }
        th { background: #ece9d8; position: sticky; top: 0; }
        tr:hover { background: #f0f0f0; }
        .text-center { text-align: center; }
        
        #loadingOverlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5); z-index: 99999; justify-content: center;
            align-items: center; flex-direction: column; color: #fff; font-family: Tahoma, Arial, sans-serif;
        }
        .spinner {
            border: 6px solid #f3f3f3; border-top: 6px solid #000080; border-radius: 50%;
            width: 50px; height: 50px; animation: spin 1s linear infinite; margin-bottom: 10px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div id="loadingOverlay">
    <div class="spinner"></div>
    <div style="font-size: 14px; font-weight: bold;" id="loadingText">Sedang memproses... Harap tunggu...</div>
</div>

<div class="topbar">
    <div>AUTO GENERATE & TRANSFER DELIVERY INSTRUCTION</div>
    <div>
        User: <?php echo h($dbUser); ?>
        <a href="manual_di.php">Kembali ke Form Utama</a>
    </div>
</div>

<div class="layout-container">
    
    <!-- KOLOM KIRI: GENERATE -->
    <div class="panel left-panel">
        <div class="title">1. Generate Header & Detail ke DI_TEMP</div>

        <?php if ($msgGenerate != "") { ?>
            <div class="<?php echo ($statGenerate == 'success') ? 'alert-success' : 'alert-error'; ?>">
                <?php echo $msgGenerate; ?>
            </div>
        <?php } ?>

        <form method="POST" action="" id="generateForm">
            <input type="hidden" name="ACTION_TYPE" id="genActionType" value="generate">
            <input type="hidden" name="CLEAR_PASSWORD" id="clearPassword" value="">

            <div class="form-grid">
                <label>CUSTOMER CODE :</label>
                <div class="autocomplete-wrap">
                    <input type="text" id="CUST_SEARCH" placeholder="Kosongkan untuk SEMUA customer..." autocomplete="off">
                    <input type="hidden" name="CUST_CODE" id="CUST_CODE">
                    <div id="custSuggest" class="autocomplete-list"></div>
                    <div class="note">* Data yang sudah ada (Cust+Tgl+No.Order) tidak akan digenerate ulang.</div>
                </div>

                <label>START DATE :</label>
                <input type="date" name="START_DATE" value="<?php echo h($firstDayOfMonth); ?>" required>

                <label>DI DATE :</label>
                <input type="date" name="DI_DATE" value="<?php echo h($today); ?>" required>

                <label>ORDER NO (Ops) :</label>
                <input type="text" name="DI_ORDERNO" placeholder="Nomor PO / Order khusus...">
            </div>

            <div class="form-grid" style="align-items: flex-start;">
                <label></label>
                <div class="checkbox-container">
                    <input type="checkbox" name="FORCE_APPEND" id="FORCE_APPEND">
                    <label for="FORCE_APPEND">MODE TAMBAH (Force Append)</label>
                </div>
                <label></label>
                <div class="note" style="margin-top:-5px;">- Abaikan pengecekan duplikat (Dapat menyebabkan dobel data).</div>
            </div>

            <div class="button-row">
                <button type="button" class="btn-danger" id="btnClearOnly">KOSONGKAN TEMP</button>
                <button type="submit" id="btnGen">GENERATE DATA</button>
            </div>
        </form>
    </div>

    <!-- KOLOM KANAN: TRANSFER -->
    <div class="panel right-panel">
        <div class="title">2. Daftar Temporary (Transfer ke Original)</div>

        <?php if ($msgTransfer != "") { ?>
            <div class="<?php echo ($statTransfer == 'success') ? 'alert-success' : 'alert-error'; ?>">
                <?php echo $msgTransfer; ?>
            </div>
        <?php } ?>

        <form method="POST" action="" id="transferForm">
            <input type="hidden" name="ACTION_TYPE" id="transferActionType" value="">
            
            <div class="toolbar">
                <div>
                    <button type="button" class="btn-primary" onclick="submitTransfer('transfer_selected')">Kirim Terpilih</button>
                    <button type="button" class="btn-success" onclick="submitTransfer('transfer_all')" style="margin-left: 10px;">Kirim Semua Data</button>
                </div>
                <div style="font-weight: bold; font-size: 13px;">
                    Total Data: <?php echo count($temp_data); ?>
                </div>
            </div>

            <div style="flex: 1; overflow-y: auto; border: 1px solid #808080; border-top: none;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30px; text-align: center;">
                                <input type="checkbox" id="checkAll" onclick="toggleCheckboxes(this)">
                            </th>
                            <th class="text-center" style="width: 30px;">No</th>
                            <th>No. DI</th>
                            <th>No. Invoice</th>
                            <th>Customer</th>
                            <th class="text-center">Total Part</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($temp_data)) { ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 20px; font-weight: bold; color: #555;">Tidak ada data temporary yang tersisa.</td>
                            </tr>
                        <?php } else { ?>
                            <?php $no = 1; foreach ($temp_data as $row) { ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="DI_IDS[]" value="<?php echo h($row['DI_ID']); ?>" class="rowCheckbox">
                                    </td>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td style="font-weight: bold; color: #000080;"><?php echo h($row['DI_NO']); ?></td>
                                    <td><?php echo h($row['DI_INVNO']); ?></td>
                                    <td><?php echo h($row['CUST_COMP']); ?></td>
                                    <td class="text-center"><?php echo h($row['TOTAL_ITEMS']); ?> Part(s)</td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

</div>

<script>
// ================= SCRIPT KIRI (GENERATE) =================
document.getElementById('generateForm').onsubmit = function() {
    if (document.getElementById('genActionType').value === 'generate') {
        document.getElementById('loadingText').innerText = "Sedang generate data ke tabel temporary... Harap tunggu...";
        document.getElementById('loadingOverlay').style.display = 'flex';
    }
};

document.getElementById('btnClearOnly').onclick = function() {
    if (confirm("PERHATIAN!\nTindakan ini akan MENGHAPUS SEMUA DATA di tabel temporary.\n\nLanjutkan?")) {
        var pwd = prompt("Masukkan password untuk mengosongkan data:");
        if (pwd === 'q9tj9') {
            document.getElementById('genActionType').value = 'clear_only';
            document.getElementById('clearPassword').value = pwd;
            document.getElementById('loadingText').innerText = "Mengosongkan data temporary... Harap tunggu...";
            document.getElementById('loadingOverlay').style.display = 'flex';
            document.getElementById('generateForm').submit();
        } else if (pwd !== null) {
            alert("Password yang Anda masukkan salah!");
        }
    }
};

var custItems = [];
function ajaxPost(url, data, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4) callback(xhr.status, xhr.responseText);
    };
    xhr.send(data);
}
function enc(value) { return encodeURIComponent(value == null ? "" : value); }
function htmlEncode(value) {
    return String(value == null ? "" : value).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/'/g, "&#039;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}
function setCustomer(c) {
    document.getElementById("CUST_CODE").value = c.CUST_CODE;
    document.getElementById("CUST_SEARCH").value = c.CUST_CODE + " - " + c.CUST_COMP;
    document.getElementById("custSuggest").style.display = "none";
}
document.getElementById("CUST_SEARCH").onkeyup = function () {
    var q = this.value;
    if (q.length < 1) { 
        document.getElementById("CUST_CODE").value = "";
        document.getElementById("custSuggest").style.display = "none"; 
        return; 
    }
    ajaxPost("ajax_customer_autocomplete.php", "q=" + enc(q), function (status, responseText) {
        if (status != 200) return;
        try {
            var items = JSON.parse(responseText);
            var box = document.getElementById("custSuggest");
            box.innerHTML = "";
            custItems = items;
            if (!items || items.length == 0) { box.style.display = "none"; return; }
            for (var i = 0; i < items.length; i++) {
                var div = document.createElement("div");
                div.className = "autocomplete-item";
                div.setAttribute("data-index", i);
                div.innerHTML = htmlEncode(items[i].CUST_CODE) + " " + htmlEncode(items[i].CUST_COMP);
                div.onclick = function () {
                    setCustomer(custItems[parseInt(this.getAttribute("data-index"), 10)]);
                };
                box.appendChild(div);
            }
            box.style.display = "block";
        } catch (e) {}
    });
};

// ================= SCRIPT KANAN (TRANSFER) =================
function toggleCheckboxes(source) {
    var checkboxes = document.getElementsByClassName('rowCheckbox');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}

function submitTransfer(action) {
    if (action === 'transfer_selected') {
        var checkboxes = document.getElementsByClassName('rowCheckbox');
        var isChecked = false;
        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) { isChecked = true; break; }
        }
        if (!isChecked) {
            alert("Pilih setidaknya satu data yang ingin ditransfer!");
            return;
        }
        if (!confirm("Pindahkan data TERPILIH ke tabel Original?")) return;
    } 
    else if (action === 'transfer_all') {
        <?php if (empty($temp_data)) { ?>
            alert("Tidak ada data untuk ditransfer!");
            return;
        <?php } ?>
        if (!confirm("PERHATIAN!\nPindahkan SEMUA data temporary ke tabel Original?")) return;
    }

    document.getElementById('transferActionType').value = action;
    document.getElementById('loadingText').innerText = "Sedang memindahkan data... Harap tunggu...";
    document.getElementById('loadingOverlay').style.display = 'flex';
    document.getElementById('transferForm').submit();
}
</script>

</body>
</html>