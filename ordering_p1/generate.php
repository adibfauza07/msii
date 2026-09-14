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
 $dbUser = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : '';

 $message = "";
 $statusType = "";

// =========================================================================
// 1. FUNGSI LOGIKA PENOMORAN
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
// 2. PROSES UTAMA (SUBMIT ACTION)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action       = isset($_POST['ACTION_TYPE']) ? trim($_POST['ACTION_TYPE']) : 'generate';
    $cust_code    = isset($_POST['CUST_CODE']) ? trim($_POST['CUST_CODE']) : '';
    $start_raw    = isset($_POST['START_DATE']) ? trim($_POST['START_DATE']) : $firstDayOfMonth;
    $end_raw      = isset($_POST['DI_DATE']) ? trim($_POST['DI_DATE']) : $today;
    $order_no     = isset($_POST['DI_ORDERNO']) ? trim($_POST['DI_ORDERNO']) : '';
    $force_append = isset($_POST['FORCE_APPEND']) ? true : false; 

    $start_date = date('Ymd', strtotime($start_raw));
    $end_date   = date('Ymd', strtotime($end_raw));

    if ($action === 'clear_only') {
        sqlsrv_begin_transaction($conn);
        try {
            sqlsrv_query($conn, "DELETE FROM dbo.DI_PART_TEMP");
            sqlsrv_query($conn, "DELETE FROM dbo.DI_TEMP");
            sqlsrv_commit($conn);
            $message = "Data temporary (DI_TEMP & DI_PART_TEMP) berhasil dikosongkan sepenuhnya.";
            $statusType = "success";
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $message = "Gagal mengosongkan data: " . $e->getMessage();
            $statusType = "error";
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
            $message = "Tidak ada customer yang ditemukan!";
            $statusType = "error";
        } else {
            $success_count = 0;
            $skipped_count = 0;
            $error_messages = array();

            foreach ($customers_to_process as $cust) {
                $c_id   = intval($cust['CUST_ID']);
                $c_code = trim($cust['CUST_CODE']);
                $c_abbr = trim($cust['CUST_ABBR']);

                // PERBAIKAN LOGIKA CEK DUPLIKAT
                if (!$force_append) {
                    $sql_cek_exist = "
                        SELECT TOP 1 DI_ID FROM dbo.DI_TEMP 
                        WHERE CUST_CODE = ? 
                          AND DI_START_DATE = ? 
                          AND DI_DATE = ? 
                          AND ISNULL(DI_ORDERNO, '') = ?
                    ";
                    $stmt_cek = sqlsrv_query($conn, $sql_cek_exist, array($c_code, $start_raw, $end_date, $order_no));
                    
                    if ($stmt_cek !== false && sqlsrv_has_rows($stmt_cek)) {
                        $skipped_count++;
                        continue; 
                    }
                }

                // PERBAIKAN UTAMA: Panggil SP dengan Lookback Start Date
                // Kita menggunakan tanggal awal yang jauh (20000101) agar SP mengambil data outstanding masa lalu
                $lookback_start_date = '20000101';
                
                $sql_chk = "SET NOCOUNT ON; EXEC dbo.SP_DELIVERY_INSTRUCTION_PO1 ?, ?, ?";
                // Parameter: CUST_CODE, START_DATE (Lookback), END_DATE
                $stmt_chk = sqlsrv_query($conn, $sql_chk, array($c_code, $lookback_start_date, $end_date));
                
                if ($stmt_chk === false) {
                    continue; 
                }

                $sp_rows = array();
                while ($s_row = sqlsrv_fetch_array($stmt_chk, SQLSRV_FETCH_ASSOC)) {
                    $sp_rows[] = $s_row;
                }

                if (empty($sp_rows)) {
                    continue; 
                }

                // =========================================================================
                // LOGIKA GROUPING BERDASARKAN LOKASI (KHUSUS D1057)
                // =========================================================================
                $grouped_rows = array();
                foreach ($sp_rows as $sp_row) {
                    $loc = isset($sp_row['LOCATION']) ? trim($sp_row['LOCATION']) : '';
                    
                    $group_key = ($c_code === 'D1057') ? ($loc == '' ? 'BLANK' : $loc) : 'ALL';
                    
                    if (!isset($grouped_rows[$group_key])) {
                        $grouped_rows[$group_key] = array();
                    }
                    $grouped_rows[$group_key][] = $sp_row;
                }

                sqlsrv_begin_transaction($conn);

                try {
                    $offset = 0; 

                    // Looping per group lokasi
                    foreach ($grouped_rows as $group_key => $group_items) {

                        // GENERATE HEADER NO
                        $di_no = GetNextDINo($conn, $end_raw, $offset);

                        if ($c_id == 275) {
                            $di_dsno = GenerateDSNo_275($conn, $end_raw, $offset);
                            $di_invno = $di_dsno; 
                        } else {
                            if ($c_abbr == '') {
                                throw new Exception("Customer Abbr kosong.");
                            }
                            $di_dsno = GenerateDSNo_Other($conn, $c_abbr, $end_raw, $offset);
                            $di_invno = str_replace('/DS/', '/INV/', $di_dsno); 
                        }

                        // Insert Header ke `DI_TEMP`
                        $sql_ins_di = "
                            INSERT INTO dbo.DI_TEMP 
                            (DI_NO, CUST_ID, CUST_CODE, DI_START_DATE, DI_DATE, DI_INVNO, DI_DSNO, DI_ORDERNO, DI_POSTED) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0);
                            SELECT SCOPE_IDENTITY() AS NEW_DI_ID;
                        ";
                        $stmt_di = sqlsrv_query($conn, $sql_ins_di, array($di_no, $c_id, $c_code, $start_raw, $end_date, $di_invno, $di_dsno, $order_no));
                        
                        if ($stmt_di === false) {
                            throw new Exception("Gagal insert header ke DI_TEMP.");
                        }

                        sqlsrv_next_result($stmt_di);
                        $row_di_id = sqlsrv_fetch_array($stmt_di, SQLSRV_FETCH_ASSOC);
                        $new_di_id = $row_di_id ? intval($row_di_id['NEW_DI_ID']) : 0;

                        if ($new_di_id <= 0) {
                            throw new Exception("Gagal mendapatkan DI_ID temporary.");
                        }

                        // Insert Detail ke `DI_PART_TEMP`
                        $lino = 1;
                        foreach ($group_items as $sp_row) {
                            $part_code = isset($sp_row['PART_NUM']) ? trim($sp_row['PART_NUM']) : '';
                            $plan_qty  = isset($sp_row['PLAN_QTY']) ? intval($sp_row['PLAN_QTY']) : 0;
                            $pb_qty    = isset($sp_row['PBQTY']) ? intval($sp_row['PBQTY']) : 0;
                            
                            if ($plan_qty > 0 && $pb_qty > 0) {
                                $qty_to_load = ($plan_qty < $pb_qty) ? $plan_qty : $pb_qty;
                            } else if ($plan_qty > 0) {
                                $qty_to_load = $plan_qty;
                            } else {
                                $qty_to_load = $pb_qty;
                            }

                            $dipa_pack = isset($sp_row['PACK_CODE']) ? trim($sp_row['PACK_CODE']) : '';
                            $std_pack  = isset($sp_row['STD_PACK_BOX']) ? intval($sp_row['STD_PACK_BOX']) : 0;
                            $location  = isset($sp_row['LOCATION']) ? trim($sp_row['LOCATION']) : '';

                            if ($qty_to_load > 0 && $part_code != '') {
                                
                                $part_code_8 = substr($part_code, 0, 8);

                                // Mengambil PRICE_ID berdasarkan Customer ID
                                $sql_part_info = "
                                    SELECT TOP 1 
                                        IT.ITEM_ID AS PART_ID, 
                                        PR.PRICE_ID 
                                    FROM dbo.ITEMS IT
                                    INNER JOIN dbo.PRICE PR ON IT.ITEM_ID = PR.PART_ID
                                    WHERE IT.ITEM_CODE = ? 
                                      AND PR.CUST_ID = ?
                                    ORDER BY PR.PRICE_ID ASC
                                ";
                                $stmt_pi = sqlsrv_query($conn, $sql_part_info, array($part_code, $c_id));
                                
                                $part_id = 0;
                                $price_id = 0;
                                
                                if ($stmt_pi !== false && $pi_row = sqlsrv_fetch_array($stmt_pi, SQLSRV_FETCH_ASSOC)) {
                                    $part_id  = intval($pi_row['PART_ID']);
                                    $price_id = intval($pi_row['PRICE_ID']);
                                }

                                // Insert termasuk BDQTY (PO Bal)
                                $sql_ins_part = "
                                    INSERT INTO dbo.DI_PART_TEMP 
                                    (DI_ID, DIPA_LINO, PART_CODE, PART_ID, DIPA_QTY, PACK_ID, DIPA_PACK, DIPA_PQTY, BDQTY, PRICE_ID, LOCATION, DIPA_POSTED, IS_MANUAL)
                                    VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 0, 0)
                                ";
                                
                                sqlsrv_query($conn, $sql_ins_part, array(
                                    $new_di_id, 
                                    $lino, 
                                    $part_code_8, 
                                    $part_id, 
                                    $qty_to_load, 
                                    substr($dipa_pack, 0, 10), 
                                    $std_pack, 
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
                $message = "SELESAI! Berhasil generate/tambahkan <strong>$success_count</strong> data baru.";
                if ($skipped_count > 0 && !$force_append) {
                    $message .= " (<strong>$skipped_count</strong> data dilewati karena sudah ada (cek Customer, Tanggal & No.Order).)";
                }
                if (!empty($error_messages)) {
                    $message .= "<br>Catatan error: " . implode(", ", $error_messages);
                }
                $statusType = "success";
            } else {
                $message = "Tidak ada data baru yang digenerate.";
                $statusType = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Auto Generate Delivery Instruction</title>
    <style>
        body { margin: 0; padding: 0; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 12px; color: #000000; }
        .topbar { background: #000080; color: #ffffff; padding: 7px 10px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: #ffffff; text-decoration: none; margin-left: 12px; }
        .main-window { width: 650px; max-width: calc(100% - 20px); margin: 30px auto; border: 2px solid #808080; background: #d4d0c8; padding: 15px; box-sizing: border-box; box-shadow: 3px 3px 5px #808080; }
        .title { font-weight: bold; margin-bottom: 12px; font-size: 14px; border-bottom: 1px solid #808080; padding-bottom: 5px; }
        .form-grid { display: grid; grid-template-columns: 140px 1fr; gap: 8px; align-items: center; margin-bottom: 10px; }
        input, select { height: 24px; border: 1px solid #808080; background: #ffffff; font-family: Tahoma, Arial, sans-serif; font-size: 12px; padding: 2px 5px; box-sizing: border-box; width: 100%; }
        button { font-family: Tahoma, Arial, sans-serif; font-size: 12px; background: #d4d0c8; border: 2px outset #ffffff; padding: 6px 14px; cursor: pointer; font-weight: bold; }
        button:active { border: 2px inset #ffffff; }
        .btn-danger { background: #e0c2c2; color: #800000; }
        .button-row { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; border-top: 1px solid #808080; padding-top: 10px; }
        .checkbox-container { display: flex; align-items: center; gap: 5px; font-size: 11px; margin-top: 5px; font-weight: bold; color: #000080; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 12px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 12px; }
        .autocomplete-wrap { position: relative; width: 100%; }
        .autocomplete-list { display: none; position: absolute; z-index: 9999; top: 24px; left: 0; right: 0; max-height: 140px; overflow-y: auto; background: #c6d8e8; border: 1px solid #808080; }
        .autocomplete-item { padding: 4px; cursor: pointer; border-bottom: 1px solid #808080; }
        .autocomplete-item:hover { background: #316ac5; color: #ffffff; }
        .note { font-size: 11px; color: #555; margin-top: 4px; }
        
        #loadingOverlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: #fff;
            font-family: Tahoma, Arial, sans-serif;
        }
        .spinner {
            border: 6px solid #f3f3f3;
            border-top: 6px solid #000080;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<div id="loadingOverlay">
    <div class="spinner"></div>
    <div style="font-size: 14px; font-weight: bold;" id="loadingText">Sedang memproses... Harap tunggu...</div>
</div>

<div class="topbar">
    <div>AUTO GENERATE DELIVERY INSTRUCTION</div>
    <div>
        User: <?php echo h($dbUser); ?>
        <a href="manual_di.php">Kembali ke Form Utama</a>
    </div>
</div>

<div class="main-window">
    <div class="title">Batch Generate Header & Detail ke DI_TEMP & DI_PART_TEMP</div>

    <?php if ($message != "") { ?>
        <div class="<?php echo ($statusType == 'success') ? 'alert-success' : 'alert-error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php } ?>

    <form method="POST" action="" id="generateForm">
        <input type="hidden" name="ACTION_TYPE" id="actionType" value="generate">

        <div class="form-grid">
            <label>CUSTOMER CODE :</label>
            <div class="autocomplete-wrap">
                <input type="text" id="CUST_SEARCH" placeholder="Kosongkan untuk generate SEMUA customer..." autocomplete="off">
                <input type="hidden" name="CUST_CODE" id="CUST_CODE">
                <div id="custSuggest" class="autocomplete-list"></div>
                <div class="note">* Data yang sudah ada (Customer+Tanggal+No.Order) tidak akan digenerate ulang.</div>
            </div>

            <label>START DATE :</label>
            <input type="date" name="START_DATE" value="<?php echo h($firstDayOfMonth); ?>" required>

            <label>DI DATE :</label>
            <input type="date" name="DI_DATE" value="<?php echo h($today); ?>" required>

            <label>ORDER NO (Opsional) :</label>
            <input type="text" name="DI_ORDERNO" placeholder="Nomor PO / Order khusus...">
        </div>

        <div class="form-grid" style="align-items: flex-start;">
            <label></label>
            <div class="checkbox-container">
                <input type="checkbox" name="FORCE_APPEND" id="FORCE_APPEND">
                <label for="FORCE_APPEND">MODE TAMBAH (Force Append)</label>
                <div style="margin-left: 5px; font-weight: normal; color: #555;">- Abaikan pengecekan duplikat dan tambahkan data baru (Dapat menyebabkan duplikat jika tidak hati-hati).</div>
            </div>
        </div>

        <div class="button-row">
            <button type="button" class="btn-danger" id="btnClearOnly">KOSONGKAN DATA SEMENTARA</button>
            <button type="submit" id="btnGen">PROSES GENERATE</button>
        </div>
    </form>
</div>

<script>
document.getElementById('generateForm').onsubmit = function() {
    if (document.getElementById('actionType').value === 'generate') {
        document.getElementById('loadingText').innerText = "Sedang generate data ke tabel temporary... Harap tunggu...";
        document.getElementById('loadingOverlay').style.display = 'flex';
    }
};

document.getElementById('btnClearOnly').onclick = function() {
    if (confirm("PERHATIAN!\nTindakan ini akan MENGHAPUS SEMUA DATA SEMENTARA di tabel DI_TEMP & DI_PART_TEMP.\n\nLanjutkan?")) {
        document.getElementById('actionType').value = 'clear_only';
        document.getElementById('loadingText').innerText = "Mengosongkan data temporary... Harap tunggu...";
        document.getElementById('loadingOverlay').style.display = 'flex';
        document.getElementById('generateForm').submit();
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
</script>

</body>
</html>