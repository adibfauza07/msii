<?php
require_once __DIR__ . '/../config/database_p1.php';

// CEK AKTIF PLANT
$isPlant1 = true;
if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') {
    $isPlant1 = false;
}

$TABEL_BC = "BC_TRANS"; 

// ==================================================================================
// BAGIAN 1: PROSES DATA (POST)
// ==================================================================================

if (isset($_POST['btnHapusTransaksi'])) {
    $idToDelete = $_POST['hapus_id'];
    if ($idToDelete) {
        sqlsrv_begin_transaction($conn);
        try {
            sqlsrv_query($conn, "DELETE FROM INV_TRAN WHERE TRAN_ID = ?", array($idToDelete));
            
            if ($isPlant1) {
                // Hapus data BC berdasarkan TRAN_DOC milik TRAN_ID yang mau dihapus
                sqlsrv_query($conn, "DELETE FROM $TABEL_BC WHERE TRAN_DOC = (SELECT TRAN_DOC FROM TRANS WHERE TRAN_ID = ?)", array($idToDelete));
            }
            
            $stmtDel = sqlsrv_query($conn, "DELETE FROM TRANS WHERE TRAN_ID = ?", array($idToDelete));
            
            if ($stmtDel) {
                sqlsrv_commit($conn);
                echo "<script>alert('Data Berhasil Dihapus!'); window.location.href='?page=transaksi';</script>";
            } else {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo "<script>alert('Gagal Hapus: " . $e->getMessage() . "');</script>";
        }
    }
}

if (isset($_POST['btnSimpanTransaksi']) || isset($_POST['btnUpdateTransaksi'])) {
    
    $isUpdate = isset($_POST['btnUpdateTransaksi']);
    $currentID = $_POST['hapus_id'];

    // VARIABEL UMUM (P1 & P2)
    $tranDoc   = substr(trim($_POST['TRAN_DOC']), 0, 30);
    $tranDate  = $_POST['TRAN_DATE'];   // Input Date
    $tranADate = $_POST['TRAN_ADATE'];  // Trans. Date
    $trtyCode  = $_POST['TRTY_CODE'];
    $supCode   = $_POST['SUP_CODE'];
    $remark    = substr(trim($_POST['TRAN_REM']), 0, 50);
    
    if ($supCode === "") $supCode = null;

    $items    = isset($_POST['item_code']) ? $_POST['item_code'] : [];
    $qtys     = isset($_POST['item_qty']) ? $_POST['item_qty'] : [];

    if (count($items) == 0) {
        echo "<script>alert('Gagal: Belum ada barang!');</script>";
    } else {
        sqlsrv_begin_transaction($conn);
        try {
            // CEK DUPLIKAT DOKUMEN
            if (!$isUpdate && $trtyCode != '14') {
                $cekDoc = sqlsrv_query($conn, "SELECT TOP 1 TRAN_DOC FROM TRANS WHERE TRAN_DOC = ?", array($tranDoc));
                if ($cekDoc && sqlsrv_fetch_array($cekDoc)) {
                    throw new Exception("No. Dokumen '{$tranDoc}' sudah dipakai! Silakan ganti No. Dokumen.");
                }
            }

            // ===================================================================
            // BLOK LOGIKA SIMPAN KHUSUS PLANT 1
            // ===================================================================
            if ($isPlant1) {
                $jenisBC   = isset($_POST['JENIS_BC']) ? $_POST['JENIS_BC'] : '';
                $nomorBC   = isset($_POST['NOMOR_BC']) ? substr(trim($_POST['NOMOR_BC']), 0, 50) : '';

                if ($isUpdate) {
                    $sqlHead = "UPDATE TRANS SET TRAN_DATE=?, TRTY_CODE=?, SUP_CODE=?, TRAN_REM=?, TRAN_ADATE=? WHERE TRAN_ID=?";
                    $paramsHead = array($tranDate, $trtyCode, $supCode, $remark, $tranADate, $currentID);
                    $stmtHead = sqlsrv_query($conn, $sqlHead, $paramsHead);
                    if ($stmtHead === false) throw new Exception("Gagal Update Header (P1):\n" . print_r(sqlsrv_errors(), true));
                    
                    // UPDATE BC MENGGUNAKAN TRAN_DOC
                    sqlsrv_query($conn, "DELETE FROM $TABEL_BC WHERE TRAN_DOC=?", array($tranDoc));
                    $stmtBC = sqlsrv_query($conn, "INSERT INTO $TABEL_BC (TRAN_DOC, JENIS_BC, NOMOR_BC) VALUES (?, ?, ?)", array($tranDoc, $jenisBC, $nomorBC));
                    if ($stmtBC === false) throw new Exception("Gagal Update Tabel BC (Cek apakah kolom TRAN_DOC ada):\n" . print_r(sqlsrv_errors(), true));

                    $targetID = $currentID;
                } else {
                    $sqlHead = "INSERT INTO TRANS (TRAN_DOC, TRAN_DATE, TRTY_CODE, SUP_CODE, TRAN_REM, TRAN_ADATE) VALUES (?, ?, ?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS ID";
                    $paramsHead = array($tranDoc, $tranDate, $trtyCode, $supCode, $remark, $tranADate);
                    $stmtHead = sqlsrv_query($conn, $sqlHead, $paramsHead);
                    if ($stmtHead === false) throw new Exception("Gagal Insert Header (P1):\n" . print_r(sqlsrv_errors(), true));

                    sqlsrv_next_result($stmtHead); 
                    $rowID = sqlsrv_fetch_array($stmtHead);
                    $targetID = $rowID['ID'];

                    // INSERT BC MENGGUNAKAN TRAN_DOC
                    $stmtBC = sqlsrv_query($conn, "INSERT INTO $TABEL_BC (TRAN_DOC, JENIS_BC, NOMOR_BC) VALUES (?, ?, ?)", array($tranDoc, $jenisBC, $nomorBC));
                    if ($stmtBC === false) throw new Exception("Gagal Insert Tabel BC (Cek apakah kolom TRAN_DOC ada):\n" . print_r(sqlsrv_errors(), true));
                }
            } 
            // ===================================================================
            // BLOK LOGIKA SIMPAN KHUSUS PLANT 2 (BERSIH DARI BC)
            // ===================================================================
            else {
                if ($isUpdate) {
                    $sqlHead = "UPDATE TRANS SET TRAN_DATE=?, TRTY_CODE=?, SUP_CODE=?, TRAN_REM=?, TRAN_ADATE=? WHERE TRAN_ID=?";
                    $paramsHead = array($tranDate, $trtyCode, $supCode, $remark, $tranADate, $currentID);
                    $stmtHead = sqlsrv_query($conn, $sqlHead, $paramsHead);
                    if ($stmtHead === false) throw new Exception("Gagal Update Header (P2):\n" . print_r(sqlsrv_errors(), true));
                    
                    $targetID = $currentID;
                } else {
                    $sqlHead = "INSERT INTO TRANS (TRAN_DOC, TRAN_DATE, TRTY_CODE, SUP_CODE, TRAN_REM, TRAN_ADATE) VALUES (?, ?, ?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS ID";
                    $paramsHead = array($tranDoc, $tranDate, $trtyCode, $supCode, $remark, $tranADate);
                    $stmtHead = sqlsrv_query($conn, $sqlHead, $paramsHead);
                    if ($stmtHead === false) throw new Exception("Gagal Insert Header (P2):\n" . print_r(sqlsrv_errors(), true));

                    sqlsrv_next_result($stmtHead); 
                    $rowID = sqlsrv_fetch_array($stmtHead);
                    $targetID = $rowID['ID'];
                }
            }

            // --- INSERT DETAIL BARANG ---
            if ($isUpdate) {
                $stmtDelDet = sqlsrv_query($conn, "DELETE FROM INV_TRAN WHERE TRAN_ID=?", array($targetID));
                if ($stmtDelDet === false) throw new Exception("Gagal Reset Detail INV_TRAN");
            }

            $sqlDet = "INSERT INTO INV_TRAN (TRAN_ID, ITEM_ID, ITEM_CODE, IT_QTY, IT_LINENO, TRAN_REMARK, ST_CODE) VALUES (?, ?, ?, ?, ?, ?, 'OK')";
            $lineNo = 1;
            foreach ($items as $index => $code) {
                $qCek = sqlsrv_query($conn, "SELECT TOP 1 ITEM_ID FROM ITEMS WHERE ITEM_CODE = ?", array($code));
                $rCek = sqlsrv_fetch_array($qCek);
                $itemID = $rCek ? $rCek['ITEM_ID'] : 0;

                $stmtDet = sqlsrv_query($conn, $sqlDet, array($targetID, $itemID, $code, $qtys[$index], $lineNo, $remark));
                if ($stmtDet === false) throw new Exception("Gagal Insert Detail baris $lineNo:\n" . print_r(sqlsrv_errors(), true));
                $lineNo++;
            }

            sqlsrv_commit($conn);
            echo "<script>alert('Data Berhasil Disimpan!'); window.location.href='?page=transaksi&id=$targetID';</script>";
            exit;

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $errorString = $e->getMessage();
            echo "<div class='container mt-3'>
                    <div class='alert alert-danger shadow' style='border: 2px solid red;'>
                        <h4 class='alert-heading text-danger fw-bold'><i class='bi bi-exclamation-triangle-fill'></i> 🚨 TERJADI ERROR DATABASE 🚨</h4>
                        <hr>
                        <p class='mb-1 fw-bold'>Detail Pesan Error dari SQL Server:</p>
                        <textarea class='form-control bg-white text-dark' rows='8' style='font-family: monospace; font-size: 13px;' readonly>" . htmlspecialchars($errorString) . "</textarea>
                    </div>
                  </div>";
        }
    }
}

// ==================================================================================
// BAGIAN 2: LOGIKA FETCH DATA
// ==================================================================================

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'view'; 
$currentID = isset($_GET['id']) ? $_GET['id'] : null;
$isEntry = ($mode == 'new' || $mode == 'edit');

$dataHeader = [
    'TRAN_ID' => '', 'TRAN_DOC' => 'AUTO', 'TRAN_DATE' => date('Y-m-d'), 'TRAN_ADATE' => date('Y-m-d'), 
    'TRTY_CODE' => '', 'SUP_CODE' => '', 'TRAN_REM' => '', 'JENIS_BC' => '', 'NOMOR_BC' => ''
];
$dataDetail = [];

if ($mode == 'new') {
    $dataHeader['TRAN_DOC'] = "TR-" . date('ymd-His'); 
} else {
    if (!$currentID) {
        $qLast = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID DESC");
        if ($qLast && $rLast = sqlsrv_fetch_array($qLast)) $currentID = $rLast['TRAN_ID'];
    }

    if ($currentID) {
        // FETCH DATA (P1 MENGGUNAKAN JOIN BERDASARKAN TRAN_DOC)
        if ($isPlant1) {
            $sqlHead = "SELECT T.*, B.JENIS_BC, B.NOMOR_BC 
                        FROM TRANS T 
                        LEFT JOIN $TABEL_BC B ON T.TRAN_DOC = B.TRAN_DOC 
                        WHERE T.TRAN_ID = ?";
        } else {
            $sqlHead = "SELECT * FROM TRANS WHERE TRAN_ID = ?";
        }
        
        $qHead = sqlsrv_query($conn, $sqlHead, array($currentID));
        if ($qHead && $rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC)) {
            $dataHeader = $rHead;
            if (!isset($dataHeader['JENIS_BC'])) $dataHeader['JENIS_BC'] = '';
            if (!isset($dataHeader['NOMOR_BC'])) $dataHeader['NOMOR_BC'] = '';

            if (isset($dataHeader['TRAN_DATE']) && $dataHeader['TRAN_DATE'] instanceof DateTime) $dataHeader['TRAN_DATE'] = $dataHeader['TRAN_DATE']->format('Y-m-d');
            if (isset($dataHeader['TRAN_ADATE']) && $dataHeader['TRAN_ADATE'] instanceof DateTime) $dataHeader['TRAN_ADATE'] = $dataHeader['TRAN_ADATE']->format('Y-m-d');
            else if (empty($dataHeader['TRAN_ADATE'])) $dataHeader['TRAN_ADATE'] = date('Y-m-d');
        }

        $sqlDetail = "SELECT T.IT_LINENO, T.IT_QTY, 
                             COALESCE(NULLIF(I.ITEM_CODE, ''), NULLIF(T.ITEM_CODE, ''), '???') as ITEM_CODE,
                             COALESCE(NULLIF(I.ITEM_NAME, ''), NULLIF(T.TRAN_REMARK, ''), '(Barang Tidak Dikenal)') as ITEM_NAME,
                             COALESCE(NULLIF(I.ITEM_UNIT, ''), '-') as ITEM_UNIT
                      FROM INV_TRAN T 
                      LEFT JOIN ITEMS I ON (T.ITEM_ID = I.ITEM_ID OR (T.ITEM_CODE IS NOT NULL AND LTRIM(RTRIM(T.ITEM_CODE)) = LTRIM(RTRIM(I.ITEM_CODE))))
                      WHERE T.TRAN_ID = ? 
                      ORDER BY T.IT_LINENO ASC";

        $qDet = sqlsrv_query($conn, $sqlDetail, array($currentID));
        if ($qDet) {
            while ($rDet = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) {
                $dataDetail[] = $rDet;
            }
        }
    }
}

$prevID = $nextID = $firstID = $lastID = null;
if (!$isEntry && $currentID) {
    $qP = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS WHERE TRAN_ID < ? ORDER BY TRAN_ID DESC", array($currentID)); if($qP && $r=sqlsrv_fetch_array($qP)) $prevID = $r['TRAN_ID'];
    $qN = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS WHERE TRAN_ID > ? ORDER BY TRAN_ID ASC", array($currentID)); if($qN && $r=sqlsrv_fetch_array($qN)) $nextID = $r['TRAN_ID'];
    $qF = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID ASC"); if($qF && $r=sqlsrv_fetch_array($qF)) $firstID = $r['TRAN_ID'];
    $qL = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID DESC"); if($qL && $r=sqlsrv_fetch_array($qL)) $lastID = $r['TRAN_ID'];
}

$optSup = ""; 
$qS = sqlsrv_query($conn, "SELECT SUP_CODE, SUP_COMP FROM SUPPLIER ORDER BY SUP_COMP ASC");
while($qS && $r=sqlsrv_fetch_array($qS)) { 
    $s = ($dataHeader['SUP_CODE'] == $r['SUP_CODE']) ? 'selected' : ''; 
    $optSup .= "<option value='{$r['SUP_CODE']}' $s>{$r['SUP_COMP']}</option>"; 
}

$optTrty = ""; 
$qT = sqlsrv_query($conn, "SELECT TRTY_CODE, TRTY_DESC FROM TRTY ORDER BY TRTY_CODE ASC");
while($qT && $r=sqlsrv_fetch_array($qT)) { 
    $s = ($dataHeader['TRTY_CODE'] == $r['TRTY_CODE']) ? 'selected' : ''; 
    $optTrty .= "<option value='{$r['TRTY_CODE']}' $s>{$r['TRTY_CODE']} - {$r['TRTY_DESC']}</option>"; 
}
?>

<form method="POST" action="" id="formTransaksi">
    <input type="hidden" name="hapus_id" value="<?php echo $currentID; ?>">

    <div class="row">
        <div class="col-12 col-lg-9">
            
            <div class="card mb-2 bg-light border">
                <div class="card-body p-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="btn-group btn-group-sm">
                        <a href="?page=transaksi&id=<?php echo $firstID; ?>" class="btn btn-outline-secondary <?php echo (!$firstID || $isEntry)?'disabled':''; ?>"><i class="bi bi-skip-backward-fill"></i></a>
                        <a href="?page=transaksi&id=<?php echo $prevID; ?>" class="btn btn-outline-secondary <?php echo (!$prevID || $isEntry)?'disabled':''; ?>"><i class="bi bi-caret-left-fill"></i></a>
                        
                        <div class="mx-1" style="min-width: 250px;">
                            <select id="cariDokumen" class="form-select form-select-sm select2" style="width: 100%;">
                                <?php if($currentID && !$isEntry): ?>
                                    <option value="<?php echo $currentID; ?>" selected><?php echo $dataHeader['TRAN_DOC']; ?></option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <a href="?page=transaksi&id=<?php echo $nextID; ?>" class="btn btn-outline-secondary <?php echo (!$nextID || $isEntry)?'disabled':''; ?>"><i class="bi bi-caret-right-fill"></i></a>
                        <a href="?page=transaksi&id=<?php echo $lastID; ?>" class="btn btn-outline-secondary <?php echo (!$lastID || $isEntry)?'disabled':''; ?>"><i class="bi bi-skip-forward-fill"></i></a>
                    </div>

                    <div class="btn-group btn-group-sm">
                        <a href="?page=transaksi&mode=new" class="btn btn-success fw-bold <?php echo ($mode=='new')?'active':''; ?>"><i class="bi bi-plus-lg"></i> BARU</a>
                        <a href="?page=transaksi&mode=edit&id=<?php echo $currentID; ?>" class="btn btn-warning fw-bold <?php echo ($mode=='edit')?'active':''; ?> <?php echo (!$currentID || $isEntry)?'disabled':''; ?>"><i class="bi bi-pencil-square"></i> EDIT</a>
                        
                        <?php if (!$isEntry && $currentID): ?>
                            <button type="submit" name="btnHapusTransaksi" class="btn btn-danger fw-bold" onclick="return confirm('Hapus Data Ini?');"><i class="bi bi-dash-lg"></i> HAPUS</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary disabled"><i class="bi bi-dash-lg"></i> HAPUS</button>
                        <?php endif; ?>
                        
                        <a href="?page=transaksi" class="btn btn-primary"><i class="bi bi-arrow-clockwise"></i></a>
                    </div>
                </div>
            </div>

            <div class="card mb-3" style="background-color: <?php echo $isEntry ? '#fff3cd' : '#e8f5e9'; ?>;">
                <div class="card-header fw-bold text-white py-1 small <?php echo $isEntry ? 'bg-warning text-dark' : 'bg-success'; ?>">
                    <i class="bi bi-receipt"></i> HEADER TRANSAKSI
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12 col-md-4 col-lg-3">
                            <label class="small fw-bold">No. Dokumen</label>
                            <input type="text" class="form-control form-control-sm fw-bold text-primary" name="TRAN_DOC" maxlength="30"
                                   value="<?php echo $dataHeader['TRAN_DOC']; ?>" required>
                        </div>
                        <div class="col-12 col-md-4 col-lg-3">
                            <label class="small fw-bold">Tipe Transaksi</label>
                            <select class="form-select form-select-sm" name="TRTY_CODE" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <?php echo $optTrty; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-lg-6">
                            <label class="small fw-bold">Supplier</label>
                            <select id="inputSupplier" class="form-select form-select-sm select2" name="SUP_CODE" style="width: 100%;" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <option value="">-- Pilih Supplier --</option>
                                <?php echo $optSup; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-3 col-lg-2">
                            <label class="small fw-bold text-danger">Input Date</label>
                            <input type="date" class="form-control form-control-sm bg-white" name="TRAN_DATE" 
                                   value="<?php echo $dataHeader['TRAN_DATE']; ?>" <?php echo !$isEntry ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-6 col-md-3 col-lg-2">
                            <label class="small fw-bold">Trans. Date</label>
                            <input type="date" class="form-control form-control-sm" name="TRAN_ADATE" 
                                   value="<?php echo $dataHeader['TRAN_ADATE']; ?>" <?php echo !$isEntry ? 'readonly' : ''; ?>>
                        </div>
                        
                        <?php if ($isPlant1): ?>
                            <div class="col-6 col-md-3 col-lg-2">
                                <label class="small fw-bold text-primary">Tipe BC</label>
                                <select class="form-select form-select-sm" name="JENIS_BC" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                    <option value="">- Non BC -</option>
                                    <option value="BC 2.3" <?php echo ($dataHeader['JENIS_BC']=='BC 2.3')?'selected':''; ?>>BC 2.3</option>
                                    <option value="BC 2.5" <?php echo ($dataHeader['JENIS_BC']=='BC 2.5')?'selected':''; ?>>BC 2.5</option>
                                    <option value="BC 2.6.1" <?php echo ($dataHeader['JENIS_BC']=='BC 2.6.1')?'selected':''; ?>>BC 2.6.1</option>
                                    <option value="BC 2.6.2" <?php echo ($dataHeader['JENIS_BC']=='BC 2.6.2')?'selected':''; ?>>BC 2.6.2</option>
                                    <option value="BC 2.7" <?php echo ($dataHeader['JENIS_BC']=='BC 2.7')?'selected':''; ?>>BC 2.7</option>
                                    <option value="BC 3.0" <?php echo ($dataHeader['JENIS_BC']=='BC 3.0')?'selected':''; ?>>BC 3.0</option>
                                    <option value="BC 4.0" <?php echo ($dataHeader['JENIS_BC']=='BC 4.0')?'selected':''; ?>>BC 4.0</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-3 col-lg-3">
                                <label class="small fw-bold text-primary">Nomor BC</label>
                                <input type="text" class="form-control form-control-sm" name="NOMOR_BC" placeholder="Ketik No BC..." maxlength="50"
                                       value="<?php echo htmlspecialchars($dataHeader['NOMOR_BC']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="col-12 col-lg-3">
                        <?php else: ?>
                            <div class="col-12 col-md-6 col-lg-8">
                        <?php endif; ?>
                        
                            <label class="small fw-bold">Keterangan (Max 50 Char)</label>
                            <input type="text" class="form-control form-control-sm" name="TRAN_REM" maxlength="50"
                                   value="<?php echo htmlspecialchars($dataHeader['TRAN_REM']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <?php if($isEntry): ?>
                <div class="card-body bg-light border-bottom py-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-lg-6">
                            <label class="small fw-bold">Cari Barang</label>
                            <select id="inputBarang" class="form-select select2-ajax" style="width: 100%;"></select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="small fw-bold">Qty</label>
                            <input type="number" id="inputQty" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="small fw-bold">Unit</label>
                            <select id="inputUnit" class="form-select form-select-sm">
                                <option value="Pcs">Pcs</option>
                                <option value="Kg">Kg</option>
                                <option value="Lbr">Lbr</option>
                                <option value="Set">Set</option>
                                <option value="Roll">Roll</option>
                                <option value="Mtr">Mtr</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-2">
                            <button type="button" class="btn btn-primary btn-sm w-100" id="btnTambahRow"><i class="bi bi-plus-lg"></i> TAMBAH</button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm mb-0" id="tabelDetail" style="font-size:13px; min-width:600px;">
                        <thead class="table-secondary">
                            <tr>
                                <th width="5%" class="text-center">No</th>
                                <th width="20%">Kode Item</th>
                                <th>Nama Barang</th>
                                <th width="10%" class="text-end">Qty</th>
                                <th width="10%" class="text-center">Unit</th>
                                <?php if($isEntry): ?><th width="10%" class="text-center">Aksi</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (!empty($dataDetail)) {
                                $no = 1;
                                foreach ($dataDetail as $row) {
                                    if ($isEntry) {
                                        echo "<tr class='row-item'>
                                            <td class='text-center align-middle'>$no</td>
                                            <td class='align-middle'><input type='hidden' name='item_code[]' value='{$row['ITEM_CODE']}'><span class='font-monospace fw-bold text-primary'>{$row['ITEM_CODE']}</span></td>
                                            <td class='align-middle'>{$row['ITEM_NAME']}</td>
                                            <td class='text-end align-middle cell-qty' style='cursor: pointer;' title='Double click'>
                                                <input type='hidden' name='item_qty[]' class='input-qty-val' value='{$row['IT_QTY']}'>
                                                <span class='txt-qty'>".number_format($row['IT_QTY'], 2)."</span>
                                                <input type='number' class='form-control form-control-sm input-qty-edit d-none' value='{$row['IT_QTY']}' step='0.01'>
                                            </td>
                                            <td class='text-center align-middle'><input type='hidden' name='item_unit[]' value='{$row['ITEM_UNIT']}'>{$row['ITEM_UNIT']}</td>
                                            <td class='text-center align-middle'>
                                                <button type='button' class='btn btn-success btn-sm btn-save-row px-2 py-0 d-none me-1'><i class='bi bi-check'></i></button>
                                                <button type='button' class='btn btn-danger btn-sm btn-hapus-row px-2 py-0'><i class='bi bi-x'></i></button>
                                            </td>
                                        </tr>";
                                    } else {
                                        echo "<tr>
                                            <td class='text-center'>$no</td>
                                            <td class='font-monospace'>{$row['ITEM_CODE']}</td>
                                            <td>{$row['ITEM_NAME']}</td>
                                            <td class='text-end fw-bold'>".number_format($row['IT_QTY'], 2)."</td>
                                            <td class='text-center'>{$row['ITEM_UNIT']}</td>
                                        </tr>";
                                    }
                                    $no++;
                                }
                            } else {
                                $colspan = $isEntry ? 6 : 5;
                                echo "<tr><td colspan='$colspan' class='text-center py-4 text-muted'>Tidak ada detail barang.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-3 mt-3 mt-lg-0">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-dark text-white text-center fw-bold py-2">STATUS AKSI</div>
                <div class="card-body p-2">
                    <div class="row g-2">
                        <?php if ($isEntry): ?>
                            <div class="col-12">
                                <?php if ($mode == 'edit'): ?>
                                    <button type="submit" name="btnUpdateTransaksi" class="btn btn-warning w-100 fw-bold" onclick="return confirm('Update?');"><i class="bi bi-save"></i> UPDATE (SIMPAN)</button>
                                <?php else: ?>
                                    <button type="submit" name="btnSimpanTransaksi" class="btn btn-success w-100 fw-bold" onclick="return confirm('Simpan?');"><i class="bi bi-save"></i> SIMPAN (POST)</button>
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <a href="?page=transaksi&id=<?php echo $currentID; ?>" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> BATAL</a>
                            </div>
                        <?php else: ?>
                            <div class="col-12">
                                <button type="button" class="btn btn-secondary w-100 py-2" disabled><i class="bi bi-lock-fill"></i> DATA TERSIMPAN</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-light text-center fw-bold py-1">CETAK DOKUMEN</div>
                <div class="card-body p-2">
                    <div class="row g-2">
                        <?php 
                        $disabled = ($currentID) ? '' : 'disabled';
                        $target = ($currentID) ? 'target="_blank"' : '';
                        
                        $trty = $dataHeader['TRTY_CODE'];
                        $clsPart = "disabled"; 
                        $lnkPart = "#";

                        if ($currentID && ($trty == '08' || $trty == '09')) {
                            $clsPart = ""; 
                            $lnkPart = "print_slip_physical.php?id=$currentID";
                        }

                        $href_icl = ($currentID) ? "print_icl.php?id=$currentID" : '#';
                        $href_mat = ($currentID) ? "print_spb.php?id=$currentID&type=MAT" : '#';
                        $href_spb = ($currentID) ? "print_spb.php?id=$currentID&type=SPB" : '#';
                        ?>
                        
                        <div class="col-12"><a href="<?php echo $href_icl; ?>" <?php echo $target; ?> class="btn btn-light border w-100 text-start btn-sm <?php echo $disabled; ?>">ICL OTOMATIS</a></div>
                        <div class="col-12"><a href="<?php echo $href_mat; ?>" <?php echo $target; ?> class="btn btn-light border w-100 text-start btn-sm <?php echo $disabled; ?>">MAT SLIP</a></div>
                        
                        <div class="col-12">
                            <a href="<?php echo $lnkPart; ?>" <?php echo $target; ?> class="btn btn-light border w-100 text-start btn-sm <?php echo $clsPart; ?>">
                                <?php echo ($clsPart=='') ? '<i class="bi bi-printer-fill text-success"></i>' : '<i class="bi bi-lock-fill text-muted"></i>'; ?> PART SLIP (08/09)
                            </a>
                        </div>

                        <div class="col-12"><a href="<?php echo $href_spb; ?>" <?php echo $target; ?> class="btn btn-light border w-100 text-start btn-sm <?php echo $disabled; ?>">SPB (Surat Jalan)</a></div>
                    </div>
                </div>
            </div>

            <div class="card mt-3 shadow-sm">
                <div class="card-header bg-secondary text-white text-center fw-bold py-1">
                    <i class="bi bi-funnel-fill"></i> REKAP LIST ICL
                </div>
                <div class="card-body p-2 bg-light">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="small fw-bold">Dari Tanggal (Opsional):</label>
                            <input type="date" id="icl_start_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-12">
                            <label class="small fw-bold">Sampai Tanggal (Opsional):</label>
                            <input type="date" id="icl_end_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 mt-2">
                            <button type="button" id="btnTampilIcl" class="btn btn-primary btn-sm w-100 fw-bold border">
                                <i class="bi bi-list-ol"></i> TAMPILKAN LIST
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</form>

<script>
$(document).ready(function() {

    $('#cariDokumen').select2({
        ajax: {
            url: 'api_cari_transaksi.php',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data }; },
            cache: true
        },
        placeholder: "Ketik No. Dokumen...",
        allowClear: true,
        width: '100%',
        minimumInputLength: 1
    });

    $('#cariDokumen').on('select2:select', function (e) {
        var idTransaksi = e.params.data.id;
        if(idTransaksi) { window.location.href = '?page=transaksi&id=' + idTransaksi; }
    });

    $('#inputSupplier').select2({
        placeholder: "-- Pilih Supplier --",
        allowClear: true,
        width: '100%'
    });

    $('#inputBarang').on('select2:select', function (e) {
        var data = e.params.data;
        var unitAsli = data.unit || 'Pcs'; 
        $('#inputUnit').val(unitAsli);
        $('#inputQty').focus(); 
    });

    $('#btnTambahRow').click(function() {
        var kode = $('#inputBarang').val();
        var nama = $('#inputBarang option:selected').text();
        var qty  = $('#inputQty').val();
        var unit = $('#inputUnit').val();

        if (!kode) { alert('Pilih barang dulu!'); return; }
        if (qty <= 0) { alert('Qty harus > 0'); return; }

        var rowCount = $('#tabelDetail tbody tr').length + 1;
        if($('#tabelDetail tbody tr td').hasClass('text-muted')) { $('#tabelDetail tbody').empty(); rowCount=1; }

        var html = `
        <tr class='row-item'>
            <td class="text-center align-middle">${rowCount}</td>
            <td class="align-middle"><input type="hidden" name="item_code[]" value="${kode}"><span class="font-monospace fw-bold text-primary">${kode}</span></td>
            <td class="align-middle">${nama}</td>
            <td class='text-end align-middle cell-qty' style='cursor: pointer;' title='Double click'>
                <input type='hidden' name='item_qty[]' class='input-qty-val' value='${qty}'>
                <span class='txt-qty'>${parseFloat(qty).toFixed(2)}</span>
                <input type='number' class='form-control form-control-sm input-qty-edit d-none' value='${qty}' step='0.01'>
            </td>
            <td class="text-center align-middle"><input type="hidden" name="item_unit[]" value="${unit}">${unit}</td>
            <td class="text-center align-middle">
                <button type='button' class='btn btn-success btn-sm btn-save-row px-2 py-0 d-none me-1'><i class='bi bi-check'></i></button>
                <button type='button' class='btn btn-danger btn-sm btn-hapus-row px-2 py-0'><i class='bi bi-x'></i></button>
            </td>
        </tr>`;
        
        $('#tabelDetail tbody').append(html);
        
        $('#inputBarang').val(null).trigger('change');
        $('#inputQty').val(0);
        
        setTimeout(function() {
            $('#inputBarang').select2('open');
        }, 100); 
    });

    $('#inputQty').on('keypress', function(e) {
        if (e.which == 13) { 
            e.preventDefault(); 
            $('#btnTambahRow').click(); 
        }
    });

    $(document).on('click', '.btn-hapus-row', function() { $(this).closest('tr').remove(); });
    
    $(document).on('dblclick', '.cell-qty', function() {
        var $td = $(this); $td.find('.txt-qty').addClass('d-none'); $td.find('.input-qty-edit').removeClass('d-none').focus().select(); $td.closest('tr').find('.btn-save-row').removeClass('d-none');
    });
    
    $(document).on('click', '.btn-save-row', function() {
        var $row = $(this).closest('tr');
        var $tdQty = $row.find('.cell-qty');
        var newVal = $tdQty.find('.input-qty-edit').val();
        $tdQty.find('.input-qty-val').val(newVal);
        $tdQty.find('.txt-qty').text(parseFloat(newVal).toFixed(2)).removeClass('d-none');
        $tdQty.find('.input-qty-edit').addClass('d-none');
        $(this).addClass('d-none');
    });
    
    $(document).on('keypress', '.input-qty-edit', function(e) { if(e.which == 13) { e.preventDefault(); $(this).closest('tr').find('.btn-save-row').click(); } });

    // FUNGSI KLIK TOMBOL REKAP ICL
    $('#btnTampilIcl').click(function() {
        var start = $('#icl_start_date').val();
        var end   = $('#icl_end_date').val();
        window.open('print_icl_list.php?start_date=' + start + '&end_date=' + end, '_blank');
    });
});
</script>