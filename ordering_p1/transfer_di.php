<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    header("Location: login.php?error=session_expired");
    exit();
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$dbUser = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : 'System';
$message = "";
$statusType = "";

// =========================================================================
// PROSES TRANSFER DATA
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ACTION_TYPE'])) {
    $action = $_POST['ACTION_TYPE'];
    $selected_ids = isset($_POST['DI_IDS']) ? $_POST['DI_IDS'] : array();

    // Jika pilih "Transfer Semua", ambil semua DI_ID yang ada di DI_TEMP
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
        $message = "Tidak ada data yang dipilih untuk ditransfer.";
        $statusType = "error";
    } else {
        $success_count = 0;
        $error_messages = array();

        foreach ($selected_ids as $temp_di_id) {
            sqlsrv_begin_transaction($conn);
            try {
                // 1. Insert Header ke tabel DI dan dapatkan ID baru
                $sql_ins_di = "
                    INSERT INTO dbo.DI (
                        DI_NO, CUST_ID, CUST_CODE, DI_START_DATE, DI_DATE,
                        DI_INVNO, DI_DSNO, DI_BCNO, DI_DSRET, DI_DSRETDATE,
                        DI_INVRET, DI_INVRETDATE, DI_BCRET, DI_BCRETDATE, DI_BCDEST,
                        DI_POSTED, DATE_CREATED, DATE_UPDATED, TRAN_ID, DI_ORDERNO
                    )
                    SELECT 
                        DI_NO, CUST_ID, CUST_CODE, DI_START_DATE, DI_DATE,
                        DI_INVNO, DI_DSNO, DI_BCNO, DI_DSRET, DI_DSRETDATE,
                        DI_INVRET, DI_INVRETDATE, DI_BCRET, DI_BCRETDATE, DI_BCDEST,
                        DI_POSTED, DATE_CREATED, DATE_UPDATED, TRAN_ID, DI_ORDERNO
                    FROM dbo.DI_TEMP
                    WHERE DI_ID = ?;
                    
                    SELECT SCOPE_IDENTITY() AS NEW_DI_ID;
                ";
                
                $stmt_di = sqlsrv_query($conn, $sql_ins_di, array($temp_di_id));
                if ($stmt_di === false) {
                    throw new Exception("Gagal insert ke tabel DI.");
                }

                // Pindah ke result set kedua untuk mengambil SCOPE_IDENTITY()
                sqlsrv_next_result($stmt_di);
                $row_new_id = sqlsrv_fetch_array($stmt_di, SQLSRV_FETCH_ASSOC);
                $new_di_id = $row_new_id ? intval($row_new_id['NEW_DI_ID']) : 0;

                if ($new_di_id <= 0) {
                    throw new Exception("Gagal mendapatkan DI_ID baru dari tabel DI.");
                }

                // 2. Insert Detail ke tabel DI_PART menggunakan NEW_DI_ID
                $sql_ins_part = "
                    INSERT INTO dbo.DI_PART (
                        DI_ID, DIPA_LINO, PART_CODE, PART_ID, SPR_CODE,
                        DIPA_QTY, PACK_ID, DIPA_PACK, DIPA_PQTY, DIPA_POSTED,
                        PRICE_ID, DIPA_CLOSE, LOCATION, IS_MANUAL, MANUAL_ORDR_ID,
                        MANUAL_ORDP_LINO, BDQTY, BC_NO
                    )
                    SELECT 
                        ?, DIPA_LINO, PART_CODE, PART_ID, SPR_CODE,
                        DIPA_QTY, PACK_ID, DIPA_PACK, DIPA_PQTY, DIPA_POSTED,
                        PRICE_ID, DIPA_CLOSE, LOCATION, IS_MANUAL, MANUAL_ORDR_ID,
                        MANUAL_ORDP_LINO, BDQTY, BC_NO
                    FROM dbo.DI_PART_TEMP
                    WHERE DI_ID = ?;
                ";
                
                $stmt_part = sqlsrv_query($conn, $sql_ins_part, array($new_di_id, $temp_di_id));
                if ($stmt_part === false) {
                    throw new Exception("Gagal insert ke tabel DI_PART.");
                }

                // 3. Hapus data dari Temporary Tables (Detail terlebih dahulu, lalu Header)
                sqlsrv_query($conn, "DELETE FROM dbo.DI_PART_TEMP WHERE DI_ID = ?", array($temp_di_id));
                sqlsrv_query($conn, "DELETE FROM dbo.DI_TEMP WHERE DI_ID = ?", array($temp_di_id));

                sqlsrv_commit($conn);
                $success_count++;

            } catch (Exception $e) {
                sqlsrv_rollback($conn);
                $error_messages[] = "ID Temp " . $temp_di_id . ": " . $e->getMessage();
            }
        }

        if ($success_count > 0) {
            $message = "Berhasil mentransfer <strong>$success_count</strong> data ke tabel original.";
            if (!empty($error_messages)) {
                $message .= "<br>Terdapat error pada beberapa data: " . implode(", ", $error_messages);
            }
            $statusType = "success";
        } else {
            $message = "Gagal mentransfer data. Error: " . implode(", ", $error_messages);
            $statusType = "error";
        }
    }
}

// =========================================================================
// AMBIL DATA DI_TEMP UNTUK DITAMPILKAN
// =========================================================================
$temp_data = array();
$sql_get_temp = "
    SELECT 
        T.DI_ID, T.DI_NO, T.CUST_CODE, T.DI_DATE, T.DI_ORDERNO,
        (SELECT COUNT(*) FROM dbo.DI_PART_TEMP P WHERE P.DI_ID = T.DI_ID) AS TOTAL_ITEMS
    FROM dbo.DI_TEMP T
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
    <title>Transfer Data Delivery Instruction</title>
    <style>
        body { margin: 0; padding: 0; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 12px; color: #000000; }
        .topbar { background: #000080; color: #ffffff; padding: 7px 10px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: #ffffff; text-decoration: none; margin-left: 12px; }
        .main-window { width: 900px; max-width: calc(100% - 20px); margin: 30px auto; border: 2px solid #808080; background: #d4d0c8; padding: 15px; box-sizing: border-box; box-shadow: 3px 3px 5px #808080; }
        .title { font-weight: bold; margin-bottom: 12px; font-size: 14px; border-bottom: 1px solid #808080; padding-bottom: 5px; }
        
        button { font-family: Tahoma, Arial, sans-serif; font-size: 12px; background: #d4d0c8; border: 2px outset #ffffff; padding: 6px 14px; cursor: pointer; font-weight: bold; }
        button:active { border: 2px inset #ffffff; }
        .btn-primary { background: #c6d8e8; color: #000080; }
        .btn-success { background: #d4edda; color: #155724; }
        
        .toolbar { display: flex; justify-content: space-between; margin-bottom: 15px; align-items: center; }
        
        table { width: 100%; border-collapse: collapse; background: #ffffff; border: 1px solid #808080; }
        th, td { border: 1px solid #808080; padding: 6px 8px; text-align: left; }
        th { background: #ece9d8; cursor: default; }
        tr:hover { background: #f0f0f0; }
        
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 12px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 12px; }
        
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
        
        .text-center { text-align: center; }
    </style>
</head>
<body>

<div id="loadingOverlay">
    <div class="spinner"></div>
    <div style="font-size: 14px; font-weight: bold;" id="loadingText">Sedang mentransfer data... Harap tunggu...</div>
</div>

<div class="topbar">
    <div>TRANSFER DELIVERY INSTRUCTION (TEMP -> ORIGINAL)</div>
    <div>
        User: <?php echo h($dbUser); ?>
        <a href="generate_di.php">Ke Generate DI</a>
    </div>
</div>

<div class="main-window">
    <div class="title">Daftar Data Delivery Instruction (Temporary)</div>

    <?php if ($message != "") { ?>
        <div class="<?php echo ($statusType == 'success') ? 'alert-success' : 'alert-error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php } ?>

    <form method="POST" action="" id="transferForm">
        <input type="hidden" name="ACTION_TYPE" id="actionType" value="">
        
        <div class="toolbar">
            <div>
                <button type="button" class="btn-primary" onclick="submitTransfer('transfer_selected')">Kirim Terpilih</button>
                <button type="button" class="btn-success" onclick="submitTransfer('transfer_all')" style="margin-left: 10px;">Kirim Semua Data</button>
            </div>
            <div style="font-weight: bold;">
                Total Data: <?php echo count($temp_data); ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 30px; text-align: center;">
                        <input type="checkbox" id="checkAll" onclick="toggleCheckboxes(this)">
                    </th>
                    <th>Temp ID</th>
                    <th>No. DI</th>
                    <th>Customer</th>
                    <th>Tanggal DI</th>
                    <th>No. Order (PO)</th>
                    <th class="text-center">Total Item</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($temp_data)) { ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 20px; font-weight: bold; color: #555;">Tidak ada data temporary yang tersisa.</td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($temp_data as $row) { ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="DI_IDS[]" value="<?php echo h($row['DI_ID']); ?>" class="rowCheckbox">
                            </td>
                            <td><?php echo h($row['DI_ID']); ?></td>
                            <td style="font-weight: bold;"><?php echo h($row['DI_NO']); ?></td>
                            <td><?php echo h($row['CUST_CODE']); ?></td>
                            <td><?php echo $row['DI_DATE'] ? $row['DI_DATE']->format('d-M-Y') : ''; ?></td>
                            <td><?php echo h($row['DI_ORDERNO']); ?></td>
                            <td class="text-center"><?php echo h($row['TOTAL_ITEMS']); ?> Part(s)</td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </form>
</div>

<script>
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
                if (checkboxes[i].checked) {
                    isChecked = true;
                    break;
                }
            }
            if (!isChecked) {
                alert("Pilih setidaknya satu data yang ingin ditransfer!");
                return;
            }
            if (!confirm("Anda yakin ingin memindahkan data TERPILIH ke tabel Original?")) {
                return;
            }
        } 
        else if (action === 'transfer_all') {
            <?php if (empty($temp_data)) { ?>
                alert("Tidak ada data untuk ditransfer!");
                return;
            <?php } ?>
            
            if (!confirm("PERHATIAN!\nAnda yakin ingin memindahkan SEMUA data di tabel temporary ke tabel Original?")) {
                return;
            }
        }

        document.getElementById('actionType').value = action;
        document.getElementById('loadingOverlay').style.display = 'flex';
        document.getElementById('transferForm').submit();
    }
</script>

</body>
</html>