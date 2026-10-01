<?php
// Tambahkan 2 baris ini untuk mencegah timeout dan kehabisan memori
set_time_limit(0); 
ini_set('memory_limit', '1024M'); 

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

// Default range tanggal untuk filter Print Temp
$print_start_val = $firstDayOfMonth;
$print_end_val   = $today;

function GetNextDINo($conn, $UseDate, $offset = 0) {
    $ts = strtotime($UseDate);
    if ($ts === false) $ts = time();
    $yymm = date('y', $ts) . date('m', $ts); 
    
    $minNo = intval($yymm . "001");  
    $pattern = $yymm . "%";

    $sql = "
        SELECT MAX(CAST(DI_NO AS INT)) AS MaxNo FROM (
            SELECT TOP 1 DI_NO FROM dbo.DI 
            WHERE DI_NO LIKE ? AND DI_NO NOT LIKE '%[^0-9]%' 
            ORDER BY DI_ID DESC
            
            UNION
            
            SELECT TOP 1 DI_NO FROM dbo.DI_TEMP 
            WHERE DI_NO LIKE ? AND DI_NO NOT LIKE '%[^0-9]%' 
            ORDER BY DI_ID DESC
        ) t
    ";
    
    $stmt = sqlsrv_query($conn, $sql, array($pattern, $pattern));
    
    $lastNo = 0;
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['MaxNo'] != null) {
            $lastNo = intval($row['MaxNo']);
        }
    }

    if ($lastNo == 0) {
        return $minNo + $offset; 
    } else {
        return $lastNo + 1; 
    }
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
// 2. PROSES POST (AJAX ENTER SAVE, GENERATE, TRANSFER, & UPLOAD PDF KOITO)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['ACTION_TYPE']) ? trim($_POST['ACTION_TYPE']) : '';

    // ---------------------------------------------------------
    // AJAX: SIMPAN SAAT TEKAN ENTER PADA INPUT MANUAL DS NO / INV NO
    // ---------------------------------------------------------
    if ($action === 'update_single_dsno') {
        $di_id = isset($_POST['DI_ID']) ? intval($_POST['DI_ID']) : 0;
        $new_dsno = isset($_POST['NEW_DSNO']) ? trim($_POST['NEW_DSNO']) : '';

        if ($di_id > 0 && $new_dsno !== '') {
            $new_invno = str_replace('/DS/', '/INV/', $new_dsno);
            $sql = "UPDATE dbo.DI_TEMP SET DI_DSNO = ?, DI_INVNO = ? WHERE DI_ID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($new_dsno, $new_invno, $di_id));
            if ($stmt) {
                echo "OK";
            } else {
                echo "ERROR";
            }
        } else {
            echo "INVALID";
        }
        exit(); 
    }

    // ---------------------------------------------------------
    // AJAX: SIMPAN SAAT TEKAN ENTER PADA INPUT MANUAL DI NO
    // ---------------------------------------------------------
    if ($action === 'update_single_dino') {
        $di_id = isset($_POST['DI_ID']) ? intval($_POST['DI_ID']) : 0;
        $new_dino = isset($_POST['NEW_DINO']) ? trim($_POST['NEW_DINO']) : '';

        if ($di_id > 0 && $new_dino !== '') {
            $sql = "UPDATE dbo.DI_TEMP SET DI_NO = ? WHERE DI_ID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($new_dino, $di_id));
            if ($stmt) {
                echo "OK";
            } else {
                echo "ERROR";
            }
        } else {
            echo "INVALID";
        }
        exit(); 
    }

    // ---------------------------------------------------------
    // A. LOGIKA UPLOAD PDF KOITO (CUST_ID = 181)
    // ---------------------------------------------------------
    if ($action === 'upload_pdf') {
        if (isset($_FILES['file_pdf']) && $_FILES['file_pdf']['error'] == 0) {
            $di_date_pdf = isset($_POST['DI_DATE_PDF']) ? trim($_POST['DI_DATE_PDF']) : $today;
            $file_tmp = $_FILES['file_pdf']['tmp_name'];
            
            // Otomatis Start Date adalah tanggal 1 di bulan yang dipilih pada DI_DATE_PDF
            $start_date_pdf = date('Y-m-01', strtotime($di_date_pdf));
            
            // Set range print agar langsung mengikuti bulan yang baru saja diupload
            $print_start_val = $start_date_pdf;
            $print_end_val   = $di_date_pdf;

            try {
                $exePath = __DIR__ . '/assets/pdftotext.exe'; 
                if (!file_exists($exePath)) {
                    throw new Exception("File pdftotext.exe tidak ditemukan di jalur: $exePath");
                }

                $pdfPath = escapeshellarg($file_tmp);
                $text = shell_exec("\"$exePath\" -layout $pdfPath -");
                
                if (empty(trim($text))) {
                    throw new Exception("Gagal membaca teks dari PDF. Pastikan file valid.");
                }
                
                $cycle_num = 1; 
                if (preg_match('/Cycle\s*\|\s*(\d+)/i', $text, $cycle_match)) {
                    $cycle_num = intval($cycle_match[1]);
                }
                $cycle_prefix = "C" . $cycle_num; 
                
                $grouped_items = array();
                $lines = explode("\n", $text);

                foreach ($lines as $line) {
                    if (preg_match('/([A-Z0-9]{4,5}-[A-Z0-9]{5}-\d{2}).*?(\d{10})\s+(\d+)\s+(\d+)/', $line, $item_match)) {
                        $part_no_pdf = trim($item_match[1]);
                        $po_no_pdf   = trim($item_match[2]);
                        $qty_pdf     = intval($item_match[3]); 
                        $box_pdf     = intval($item_match[4]); 
                        
                        if ($qty_pdf > 0) {
                            if (!isset($grouped_items[$po_no_pdf])) {
                                $grouped_items[$po_no_pdf] = array();
                            }
                            $grouped_items[$po_no_pdf][] = array(
                                'PART_NUM' => $part_no_pdf,
                                'QTY'      => $qty_pdf,
                                'BOX_QTY'  => $box_pdf
                            );
                        }
                    }
                }
                
                if (empty($grouped_items)) throw new Exception("Gagal mengekstrak data Part & Qty dari PDF.");

                $c_id = 181;
                $sql_cust = "SELECT CUST_CODE, CUST_ABBR FROM dbo.CUST WHERE CUST_ID = ?";
                $stmt_cust = sqlsrv_query($conn, $sql_cust, array($c_id));
                $c_row = sqlsrv_fetch_array($stmt_cust, SQLSRV_FETCH_ASSOC);
                if (!$c_row) throw new Exception("Customer Koito (ID 181) tidak ditemukan di database.");
                
                $c_code = trim($c_row['CUST_CODE']);
                $c_abbr = trim($c_row['CUST_ABBR']);

                // Tarik data dari SP_DELIVERY_INSTRUCTION_PO2 menggunakan range awal bulan DI_DATE_PDF
                $sp_start_date = date('Ymd', strtotime($start_date_pdf));
                $sp_end_date   = date('Ymd', strtotime($di_date_pdf));
                
                $sp_koito_map = array();
                $sql_sp = "SET NOCOUNT ON; EXEC dbo.SP_DELIVERY_INSTRUCTION_PO2 ?, ?, ?";
                $stmt_sp = sqlsrv_query($conn, $sql_sp, array($c_code, $sp_start_date, $sp_end_date));
                if ($stmt_sp !== false) {
                    while ($sp_r = sqlsrv_fetch_array($stmt_sp, SQLSRV_FETCH_ASSOC)) {
                        $sp_part_num = trim(isset($sp_r['PART_NUM']) ? $sp_r['PART_NUM'] : '');
                        $sp_part_no  = trim(isset($sp_r['PART_NO']) ? $sp_r['PART_NO'] : '');
                        $sp_po       = trim(isset($sp_r['PO']) ? $sp_r['PO'] : '');
                        $sp_pbqty    = isset($sp_r['PBQTY']) ? floatval($sp_r['PBQTY']) : 0;
                        $sp_pack     = trim(isset($sp_r['PACK_CODE']) ? $sp_r['PACK_CODE'] : '');

                        $item_data = array('PBQTY' => $sp_pbqty, 'PACK_CODE' => $sp_pack);

                        if ($sp_part_num !== '' && $sp_po !== '') $sp_koito_map[$sp_part_num . '___' . $sp_po] = $item_data;
                        if ($sp_part_no !== '' && $sp_po !== '')  $sp_koito_map[$sp_part_no . '___' . $sp_po] = $item_data;
                        if ($sp_part_num !== '' && !isset($sp_koito_map[$sp_part_num])) $sp_koito_map[$sp_part_num] = $item_data;
                        if ($sp_part_no !== '' && !isset($sp_koito_map[$sp_part_no]))   $sp_koito_map[$sp_part_no] = $item_data;
                    }
                }
                
                sqlsrv_begin_transaction($conn);
                
                $base_dino = GetNextDINo($conn, $di_date_pdf);
                $base_dsno = GenerateDSNo_Other($conn, $c_abbr, $di_date_pdf);
                $ds_parts = explode('/', $base_dsno);
                $start_ds_int = intval($ds_parts[0]);
                
                $loop_index = 0; 
                $total_part_created = 0;
                $alphabet = range('A', 'Z');

                foreach ($grouped_items as $current_po_no => $items_in_po) {
                    $suffix = isset($alphabet[$loop_index]) ? $alphabet[$loop_index] : 'X';
                    $location_code = $cycle_prefix . $suffix; 
                    
                    $di_no = $base_dino + $loop_index;
                    $ds_parts[0] = sprintf('%03d', $start_ds_int + $loop_index);
                    $di_dsno = implode('/', $ds_parts);
                    $di_invno = str_replace('/DS/', '/INV/', $di_dsno);
                    
                    $sql_ins_di = "
                        INSERT INTO dbo.DI_TEMP (DI_NO, CUST_ID, CUST_CODE, DI_START_DATE, DI_DATE, DI_INVNO, DI_DSNO, DI_ORDERNO, DI_POSTED) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0);
                        SELECT SCOPE_IDENTITY() AS NEW_DI_ID;
                    ";
                    $stmt_di = sqlsrv_query($conn, $sql_ins_di, array($di_no, $c_id, $c_code, $start_date_pdf, $di_date_pdf, $di_invno, $di_dsno, $current_po_no));
                    if ($stmt_di === false) throw new Exception("Gagal insert header DI_TEMP PO: " . $current_po_no);
                    
                    sqlsrv_next_result($stmt_di);
                    $row_di_id = sqlsrv_fetch_array($stmt_di, SQLSRV_FETCH_ASSOC);
                    $new_di_id = $row_di_id ? intval($row_di_id['NEW_DI_ID']) : 0;
                    
                    $lino = 1;
                    foreach ($items_in_po as $item) {
                        $part_pdf = trim($item['PART_NUM']);
                        
                        $sql_part_info = "
                            SELECT TOP 1 
                                PV.PART_ID, PV.PART_CODE, PV.PART_NUM, PV.PART_NO, 
                                SP.PACK_ID, PK.PACK_CODE, PD.PRICE_ID
                            FROM dbo.PART_VIEW PV
                            LEFT JOIN dbo.STD_PACK SP ON (PV.PART_CODE = SP.ITEM_CODE OR PV.PART_NUM = SP.ITEM_CODE OR PV.PART_ID = SP.ITEM_ID)
                            LEFT JOIN dbo.PACK PK ON SP.PACK_ID = PK.PACK_ID
                            LEFT JOIN dbo.PRICE P ON PV.PART_ID = P.PART_ID
                            LEFT JOIN dbo.PRICE_DETAIL PD ON P.PRICE_ID = PD.PRICE_ID 
                                 AND (PD.PRDT_START IS NOT NULL AND ? >= PD.PRDT_START) 
                                 AND (PD.PRDT_END IS NULL OR ? <= PD.PRDT_END)
                            WHERE LTRIM(RTRIM(PV.PART_NO)) = ? 
                               OR LTRIM(RTRIM(PV.PART_CODE)) = ? 
                               OR LTRIM(RTRIM(PV.PART_NUM)) = ?
                            ORDER BY PD.PRDT_START DESC
                        ";
                        
                        $stmt_pi = sqlsrv_query($conn, $sql_part_info, array($di_date_pdf, $di_date_pdf, $part_pdf, $part_pdf, $part_pdf));
                        
                        if ($pi_row = sqlsrv_fetch_array($stmt_pi, SQLSRV_FETCH_ASSOC)) {
                            $part_id   = intval($pi_row['PART_ID']);
                            $part_code = trim($pi_row['PART_CODE']);
                            $pack_id   = isset($pi_row['PACK_ID']) ? intval($pi_row['PACK_ID']) : 1;
                            $price_id  = isset($pi_row['PRICE_ID']) ? intval($pi_row['PRICE_ID']) : 0;
                            
                            $db_part_num = trim((string)$pi_row['PART_NUM']);
                            $db_part_no  = trim((string)$pi_row['PART_NO']);
                            
                            $total_pcs   = $item['QTY'];
                            $total_boxes = ($item['BOX_QTY'] > 0) ? $item['BOX_QTY'] : 1;
                            
                            $po_bal = 0;
                            $dipa_pack = !empty($pi_row['PACK_CODE']) ? trim($pi_row['PACK_CODE']) : 'BB';

                            if (isset($sp_koito_map[$part_pdf . '___' . $current_po_no])) {
                                $po_bal = $sp_koito_map[$part_pdf . '___' . $current_po_no]['PBQTY'];
                                if (!empty($sp_koito_map[$part_pdf . '___' . $current_po_no]['PACK_CODE'])) {
                                    $dipa_pack = $sp_koito_map[$part_pdf . '___' . $current_po_no]['PACK_CODE'];
                                }
                            } elseif ($db_part_num !== '' && isset($sp_koito_map[$db_part_num . '___' . $current_po_no])) {
                                $po_bal = $sp_koito_map[$db_part_num . '___' . $current_po_no]['PBQTY'];
                                if (!empty($sp_koito_map[$db_part_num . '___' . $current_po_no]['PACK_CODE'])) {
                                    $dipa_pack = $sp_koito_map[$db_part_num . '___' . $current_po_no]['PACK_CODE'];
                                }
                            } elseif ($db_part_no !== '' && isset($sp_koito_map[$db_part_no . '___' . $current_po_no])) {
                                $po_bal = $sp_koito_map[$db_part_no . '___' . $current_po_no]['PBQTY'];
                                if (!empty($sp_koito_map[$db_part_no . '___' . $current_po_no]['PACK_CODE'])) {
                                    $dipa_pack = $sp_koito_map[$db_part_no . '___' . $current_po_no]['PACK_CODE'];
                                }
                            } elseif (isset($sp_koito_map[$part_pdf])) {
                                $po_bal = $sp_koito_map[$part_pdf]['PBQTY'];
                                if (!empty($sp_koito_map[$part_pdf]['PACK_CODE'])) {
                                    $dipa_pack = $sp_koito_map[$part_pdf]['PACK_CODE'];
                                }
                            } elseif ($db_part_num !== '' && isset($sp_koito_map[$db_part_num])) {
                                $po_bal = $sp_koito_map[$db_part_num]['PBQTY'];
                                if (!empty($sp_koito_map[$db_part_num]['PACK_CODE'])) {
                                    $dipa_pack = $sp_koito_map[$db_part_num]['PACK_CODE'];
                                }
                            }

                            $sql_ins_part = "
                                INSERT INTO dbo.DI_PART_TEMP 
                                (DI_ID, DIPA_LINO, PART_CODE, PART_ID, DIPA_QTY, PACK_ID, DIPA_PACK, DIPA_PQTY, BDQTY, PRICE_ID, DIPA_POSTED, IS_MANUAL, LOCATION, BC_NO, DI_ORDERNO)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?, ?, ?)
                            ";
                            sqlsrv_query($conn, $sql_ins_part, array(
                                $new_di_id, 
                                $lino, 
                                substr($part_code, 0, 8), 
                                $part_id, 
                                $total_pcs, 
                                $pack_id, 
                                substr($dipa_pack, 0, 10), 
                                $total_boxes, 
                                $po_bal, 
                                $price_id, 
                                $location_code, 
                                $current_po_no, 
                                $current_po_no
                            ));
                            $lino++;
                            $total_part_created++;
                        } else {
                            throw new Exception("Part Number <strong>" . $part_pdf . "</strong> tidak ditemukan di Master.");
                        }
                    }
                    $loop_index++;
                }
                
                sqlsrv_commit($conn);
                $msgGenerate = "Sukses Upload PDF Koito! Berhasil membuat <strong>$loop_index DI</strong> dengan total <strong>$total_part_created Part</strong>.";
                $statGenerate = "success";
                
            } catch (Exception $e) {
                sqlsrv_rollback($conn);
                $msgGenerate = "Gagal memproses PDF: " . $e->getMessage();
                $statGenerate = "error";
            }
        }
    }

    // ---------------------------------------------------------
    // B. LOGIKA GENERATE NORMAL & CLEAR SEMENTARA
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

        $print_start_val = $start_raw;
        $print_end_val   = $end_raw;

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
                $sql_all_cust = "SELECT CUST_CODE, CUST_ID, CUST_ABBR FROM dbo.CUST WHERE CUST_ID <> 181";
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
                $msgGenerate = "Tidak ada customer yang ditemukan untuk digenerate!";
                $statGenerate = "error";
            } else {
                $success_count = 0;
                $skipped_count = 0;
                $error_messages = array();

                foreach ($customers_to_process as $cust) {
                    $c_id   = intval($cust['CUST_ID']);
                    $c_code = trim($cust['CUST_CODE']);
                    $c_abbr = trim($cust['CUST_ABBR']);

                    $sql_chk = "SET NOCOUNT ON; EXEC dbo.SP_DELIVERY_INSTRUCTION_PO2 ?, ?, ?";
                    $stmt_chk = sqlsrv_query($conn, $sql_chk, array($c_code, $start_date, $end_date));
                    
                    if ($stmt_chk === false) continue; 

                    $sp_rows = array();
                    while ($s_row = sqlsrv_fetch_array($stmt_chk, SQLSRV_FETCH_ASSOC)) {
                        $sp_rows[] = $s_row;
                    }
                    if (empty($sp_rows)) continue; 

                    $grouped_rows = array();
                    foreach ($sp_rows as $sp_row) {
                        $group_key = 'ALL';
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

                            $di_no = GetNextDINo($conn, $end_raw, 0); 
                            
                            if ($c_abbr == '') throw new Exception("Customer Abbr kosong. (Cust ID: " . $c_id . ")");
                            $di_dsno = GenerateDSNo_Other($conn, $c_abbr, $end_raw, $offset);
                            $di_invno = str_replace('/DS/', '/INV/', $di_dsno); 
                            
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
                                
                                $row_po = '';
                                if (isset($sp_row['PO']) && trim((string)$sp_row['PO']) !== '') {
                                    $row_po = trim((string)$sp_row['PO']);
                                } elseif (isset($sp_row['BC_NO']) && trim((string)$sp_row['BC_NO']) !== '') {
                                    $row_po = trim((string)$sp_row['BC_NO']);
                                } elseif (isset($sp_row['ORDER_NO']) && trim((string)$sp_row['ORDER_NO']) !== '') {
                                    $row_po = trim((string)$sp_row['ORDER_NO']);
                                } else {
                                    $row_po = $header_order_no;
                                }
                                
                                if ($plan_qty > 0 && $pb_qty > 0) $qty_to_load = ($plan_qty < $pb_qty) ? $plan_qty : $pb_qty;
                                else if ($plan_qty > 0) $qty_to_load = $plan_qty;
                                else $qty_to_load = $pb_qty;

                                $dipa_pqty = isset($sp_row['STD_BOX']) ? intval($sp_row['STD_BOX']) : (isset($sp_row['STD_PACK_BOX']) ? intval($sp_row['STD_PACK_BOX']) : 0);
                                $location = '';

                                $part_id = 0; $price_id = 0; $part_code_from_view = '';
                                $pack_id = 1; 
                                $dipa_pack = isset($sp_row['PACK_CODE']) ? trim($sp_row['PACK_CODE']) : '';

                                if ($part_num != '') {
                                    $sql_part_info = "
                                        SELECT TOP 1 
                                            PV.PART_ID, PV.PRICE_ID, PV.PART_CODE, SP.PACK_ID, P.PACK_CODE
                                        FROM dbo.PART_VIEW PV
                                        LEFT JOIN dbo.STD_PACK SP ON PV.PART_CODE = SP.ITEM_CODE
                                        LEFT JOIN dbo.PACK P ON SP.PACK_ID = P.PACK_ID
                                        WHERE LTRIM(RTRIM(PV.PART_NO)) = ? OR LTRIM(RTRIM(PV.PART_CODE)) = ?
                                    ";
                                    $stmt_pi = sqlsrv_query($conn, $sql_part_info, array($part_num, $part_num));
                                    if ($stmt_pi !== false && $pi_row = sqlsrv_fetch_array($stmt_pi, SQLSRV_FETCH_ASSOC)) {
                                        $part_id  = intval($pi_row['PART_ID']);
                                        $price_id = intval($pi_row['PRICE_ID']);
                                        $part_code_from_view = trim($pi_row['PART_CODE']);
                                        
                                        if (!empty($pi_row['PACK_ID'])) $pack_id = intval($pi_row['PACK_ID']);
                                        if (empty($dipa_pack) && !empty($pi_row['PACK_CODE'])) $dipa_pack = trim($pi_row['PACK_CODE']);
                                    }
                                }

                                if ($qty_to_load > 0 && $part_num != '' && $part_no != '' && $part_name != '' && $price_id > 0) {
                                    $part_code_final = ($part_code_from_view != '') ? $part_code_from_view : substr($part_num, 0, 8);
                                    $part_code_8 = substr($part_code_final, 0, 8);

                                    $sql_ins_part = "
                                        INSERT INTO dbo.DI_PART_TEMP 
                                        (DI_ID, DIPA_LINO, PART_CODE, PART_ID, DIPA_QTY, PACK_ID, DIPA_PACK, DIPA_PQTY, BDQTY, PRICE_ID, LOCATION, DIPA_POSTED, IS_MANUAL, BC_NO, DI_ORDERNO)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?)
                                    ";
                                    sqlsrv_query($conn, $sql_ins_part, array(
                                        $new_di_id, $lino, $part_code_8, $part_id, $qty_to_load, $pack_id,                       
                                        substr($dipa_pack, 0, 10), $dipa_pqty, $pb_qty, $price_id, substr($location, 0, 30),
                                        $row_po, $row_po
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
    // C. LOGIKA TRANSFER DENGAN EDIT MANUAL DI_NO, DS_NO & BYPASS TRIGGER
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

            $edit_dsno = isset($_POST['EDIT_DSNO']) ? $_POST['EDIT_DSNO'] : array();
            $edit_dino = isset($_POST['EDIT_DINO']) ? $_POST['EDIT_DINO'] : array();

            sqlsrv_query($conn, "UPDATE dbo.CONTROL_FLAGS SET FLAG_VALUE = 1 WHERE FLAG_NAME = 'REPOSTING_MODE'");

            foreach ($selected_ids as $temp_di_id) {
                sqlsrv_begin_transaction($conn);
                try {
                    $upd_dsno = isset($edit_dsno[$temp_di_id]) ? trim($edit_dsno[$temp_di_id]) : '';
                    if ($upd_dsno !== '') {
                        $upd_invno = str_replace('/DS/', '/INV/', $upd_dsno);
                        $sql_upd_temp = "UPDATE dbo.DI_TEMP SET DI_DSNO = ?, DI_INVNO = ? WHERE DI_ID = ?";
                        sqlsrv_query($conn, $sql_upd_temp, array($upd_dsno, $upd_invno, $temp_di_id));
                    }

                    $upd_dino = isset($edit_dino[$temp_di_id]) ? trim($edit_dino[$temp_di_id]) : '';
                    if ($upd_dino !== '') {
                        $sql_upd_dino = "UPDATE dbo.DI_TEMP SET DI_NO = ? WHERE DI_ID = ?";
                        sqlsrv_query($conn, $sql_upd_dino, array($upd_dino, $temp_di_id));
                    }

                    $sql_ins_di = "
                        SET NOCOUNT ON;
                        INSERT INTO dbo.DI (
                            DI_NO, CUST_ID, CUST_CODE, DI_START_DATE, DI_DATE, DI_INVNO, DI_DSNO, DI_BCNO, DI_DSRET, DI_DSRETDATE,
                            DI_INVRET, DI_INVRETDATE, DI_BCRET, DI_BCRETDATE, DI_BCDEST, DI_POSTED, DATE_CREATED, DATE_UPDATED, TRAN_ID, DI_ORDERNO
                        )
                        SELECT 
                            DI_NO, CUST_ID, CUST_CODE, DI_START_DATE, DI_DATE, DI_INVNO, DI_DSNO, DI_BCNO, DI_DSRET, DI_DSRETDATE,
                            DI_INVRET, DI_INVRETDATE, DI_BCRET, DI_BCRETDATE, DI_BCDEST, DI_POSTED, DATE_CREATED, DATE_UPDATED, TRAN_ID, DI_ORDERNO
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

                    sqlsrv_query($conn, "DELETE FROM dbo.DI_PART_TEMP WHERE DI_ID = ?", array($temp_di_id));
                    sqlsrv_query($conn, "DELETE FROM dbo.DI_TEMP WHERE DI_ID = ?", array($temp_di_id));

                    sqlsrv_commit($conn);
                    $success_count++;

                } catch (Exception $e) {
                    sqlsrv_rollback($conn);
                    $error_messages[] = "ID Temp " . $temp_di_id . ": " . $e->getMessage();
                }
            }

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
        T.DI_DSNO, 
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
        
        /* Style untuk input inline yang bisa di-Enter */
        .input-manual { border: 1px solid #a0a0a0; background: #ffffcc; font-size: 11px; width: 100%; padding: 4px; font-family: Tahoma; transition: background-color 0.3s; }
        .input-manual:focus { background: #ffffff; border-color: #000080; outline: none; }

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
        th, td { border: 1px solid #808080; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #ece9d8; position: sticky; top: 0; z-index: 10; }
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
    
    <!-- KOLOM KIRI: GENERATE & PDF UPLOAD -->
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
                <input type="date" name="START_DATE" id="GEN_START_DATE" value="<?php echo h($firstDayOfMonth); ?>" required>

                <label>DI DATE :</label>
                <input type="date" name="DI_DATE" id="GEN_DI_DATE" value="<?php echo h($today); ?>" required>

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
        
        <!-- Form Tambahan: Upload PDF Koito -->
        <div style="margin-top: 30px; border-top: 2px solid #808080; padding-top: 15px;">
            <div class="title" style="color: #000080;">Upload PDF (Khusus Customer Koito)</div>
            <form method="POST" action="" enctype="multipart/form-data" id="uploadPdfForm">
                <input type="hidden" name="ACTION_TYPE" value="upload_pdf">
                
                <div class="form-grid">
                    <label>Pilih File PDF :</label>
                    <input type="file" name="file_pdf" accept="application/pdf" required style="border: none; padding-top: 5px; width: 100%;">
                    
                    <label>Tanggal DI :</label>
                    <input type="date" name="DI_DATE_PDF" id="DI_DATE_PDF" value="<?php echo h($today); ?>" required>
                </div>

                <div class="button-row">
                    <button type="submit" class="btn-primary" onclick="showLoadingPdf();">UPLOAD & EXTRACT PDF</button>
                </div>
            </form>
        </div>
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
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="btn-primary" onclick="submitTransfer('transfer_selected')">Kirim Terpilih</button>
                    <button type="button" class="btn-success" onclick="submitTransfer('transfer_all')">Kirim Semua Data</button>
                    
                    <!-- Form Print DI -->
                    <div style="border-left: 2px solid #808080; height: 24px; margin: 0 5px;"></div>
                    <div style="display: flex; gap: 5px; align-items: center;">
                        <span style="font-weight:bold; font-size:11px; color:#000080;">Print Temp:</span>
                        <input type="date" id="print_start" value="<?php echo h($print_start_val); ?>" style="width: 110px;">
                        <span style="font-weight:bold;">-</span>
                        <input type="date" id="print_end" value="<?php echo h($print_end_val); ?>" style="width: 110px;">
                        <button type="button" onclick="printDI()" style="padding: 4px 10px; color: #000;">Print DI</button>
                    </div>
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
                            <th style="width: 140px;">No. DI (Bisa Diedit)</th>
                            <th style="width: 280px;">No. DS / Invoice (Bisa Diedit)</th>
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
                                    
                                    <!-- INPUT EDIT MANUAL NO. DI DENGAN ENTER -->
                                    <td>
                                        <input type="text" name="EDIT_DINO[<?php echo h($row['DI_ID']); ?>]" 
                                               value="<?php echo h($row['DI_NO']); ?>" 
                                               class="input-manual" 
                                               style="font-weight: bold; color: #000080;"
                                               onkeydown="saveDiNoOnEnter(event, this, <?php echo h($row['DI_ID']); ?>)"
                                               title="Tekan ENTER untuk menyimpan No. DI">
                                        <div style="font-size: 10px; color: #808080; margin-top: 3px;">
                                            *Tekan <b>ENTER</b> untuk simpan
                                        </div>
                                    </td>
                                    
                                    <!-- INPUT EDIT MANUAL NO. DS/INV DENGAN ENTER -->
                                    <td>
                                        <input type="text" name="EDIT_DSNO[<?php echo h($row['DI_ID']); ?>]" 
                                               value="<?php echo h($row['DI_DSNO']); ?>" 
                                               class="input-manual" 
                                               onkeydown="saveDsNoOnEnter(event, this, <?php echo h($row['DI_ID']); ?>)"
                                               title="Tekan ENTER untuk menyimpan No. DS / INV">
                                        <div style="font-size: 10px; color: #808080; margin-top: 3px;">
                                            *Tekan <b>ENTER</b> untuk simpan (/INV/ otomatis)
                                        </div>
                                    </td>
                                    
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
// ================= AUTO SINKRON TANGGAL AWAL BULAN =================
// Setiap kali input "Tanggal DI" diubah, Print Temp otomatis mulai dari tgl 1 bulan tersebut
document.getElementById('DI_DATE_PDF').addEventListener('change', function() {
    var val = this.value;
    if (val) {
        var d = new Date(val);
        if (!isNaN(d.getTime())) {
            var y = d.getFullYear();
            var m = ('0' + (d.getMonth() + 1)).slice(-2);
            var firstDay = y + '-' + m + '-01';
            
            // Set otomatis input Print Temp
            document.getElementById('print_start').value = firstDay;
            document.getElementById('print_end').value = val;
        }
    }
});

// Begitu juga jika mengganti DI DATE pada form Generate manual
document.getElementById('GEN_DI_DATE').addEventListener('change', function() {
    var val = this.value;
    if (val) {
        var d = new Date(val);
        if (!isNaN(d.getTime())) {
            var y = d.getFullYear();
            var m = ('0' + (d.getMonth() + 1)).slice(-2);
            var firstDay = y + '-' + m + '-01';
            document.getElementById('GEN_START_DATE').value = firstDay;
            document.getElementById('print_start').value = firstDay;
            document.getElementById('print_end').value = val;
        }
    }
});

// ================= SCRIPT JAVASCRIPT: AJAX SAVE ENTER NO. DI =================
function saveDiNoOnEnter(e, inputElem, diId) {
    if (e.key === 'Enter' || e.keyCode === 13) {
        e.preventDefault();

        var newDiNo = inputElem.value.trim();
        inputElem.style.backgroundColor = '#e0e0e0';

        var data = "ACTION_TYPE=update_single_dino&DI_ID=" + encodeURIComponent(diId) + "&NEW_DINO=" + encodeURIComponent(newDiNo);

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4) {
                if (xhr.status == 200 && xhr.responseText.trim() === "OK") {
                    inputElem.style.backgroundColor = '#ccffcc';
                    inputElem.blur();
                    setTimeout(function() { 
                        inputElem.style.backgroundColor = '#ffffcc'; 
                    }, 1500);
                } else {
                    alert("Gagal menyimpan No. DI ke database!");
                    inputElem.style.backgroundColor = '#ffcccc';
                }
            }
        };
        xhr.send(data);
    }
}

// ================= SCRIPT JAVASCRIPT: AJAX SAVE ENTER NO. DS =================
function saveDsNoOnEnter(e, inputElem, diId) {
    if (e.key === 'Enter' || e.keyCode === 13) {
        e.preventDefault();

        var newDsNo = inputElem.value.trim();
        inputElem.style.backgroundColor = '#e0e0e0';

        var data = "ACTION_TYPE=update_single_dsno&DI_ID=" + encodeURIComponent(diId) + "&NEW_DSNO=" + encodeURIComponent(newDsNo);

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4) {
                if (xhr.status == 200 && xhr.responseText.trim() === "OK") {
                    inputElem.style.backgroundColor = '#ccffcc';
                    inputElem.blur();
                    setTimeout(function() { 
                        inputElem.style.backgroundColor = '#ffffcc'; 
                    }, 1500);
                } else {
                    alert("Gagal menyimpan No. DS ke database!");
                    inputElem.style.backgroundColor = '#ffcccc';
                }
            }
        };
        xhr.send(data);
    }
}

// ================= SCRIPT KIRI (GENERATE & PDF UPLOAD) =================
function showLoadingPdf() {
    if (document.forms['uploadPdfForm']['file_pdf'].value != '') {
        document.getElementById('loadingText').innerText = "Sedang mengekstrak file PDF dan membuat DI... Harap tunggu...";
        document.getElementById('loadingOverlay').style.display = 'flex';
    }
}

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
        if (!confirm("Data DI dan DS/Invoice yang Anda edit akan disimpan.\nPindahkan data TERPILIH ke tabel Original?")) return;
    } 
    else if (action === 'transfer_all') {
        <?php if (empty($temp_data)) { ?>
            alert("Tidak ada data untuk ditransfer!");
            return;
        <?php } ?>
        if (!confirm("PERHATIAN!\nSemua data DI dan DS/Invoice yang Anda edit akan disimpan.\nPindahkan SEMUA data temporary ke tabel Original?")) return;
    }

    document.getElementById('transferActionType').value = action;
    document.getElementById('loadingText').innerText = "Sedang menyimpan dan memindahkan data... Harap tunggu...";
    document.getElementById('loadingOverlay').style.display = 'flex';
    document.getElementById('transferForm').submit();
}

// ================= SCRIPT PRINT =================
function printDI() {
    var startDate = document.getElementById('print_start').value;
    var endDate = document.getElementById('print_end').value;
    
    if (!startDate || !endDate) {
        alert("Tanggal Start Date dan End Date untuk Print harus diisi!");
        return;
    }
    
    var url = "delivery_instruction_oto.php?START_DATE=" + enc(startDate) + "&END_DATE=" + enc(endDate);
    window.open(url, '_blank');
}
</script>

</body>
</html>