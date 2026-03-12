<?php
// MENJADI:
require_once __DIR__ . '/../config/database_p1.php';

// ==================================================================================
// BAGIAN 1: PROSES DATA (POST) - SIMPAN, UPDATE, HAPUS
// ==================================================================================

// A. HAPUS BARANG (-)
if (isset($_POST['btnHapus'])) {
    $idToDelete = $_POST['hapus_id'];
    if ($idToDelete) {
        // CEK 1: Apakah barang sudah dipakai di Transaksi?
        $cekTrans = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM INV_TRAN WHERE ITEM_CODE=?", array($idToDelete));
        $isUsedTrans = ($cekTrans && sqlsrv_fetch_array($cekTrans));

        // CEK 2: Apakah barang sudah dipakai di Stock Opname (TAGS)?
        $cekTags = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM TAGS WHERE ITEM_CODE=?", array($idToDelete));
        $isUsedTags = ($cekTags && sqlsrv_fetch_array($cekTags));

        // JIKA BARANG SUDAH DIPAKAI, TOLAK PENGHAPUSAN
        if ($isUsedTrans || $isUsedTags) {
            echo "<div class='alert alert-danger mx-3 mt-3 fw-bold'>
                    <i class='bi bi-x-circle'></i> GAGAL MENGHAPUS: Barang '{$idToDelete}' tidak bisa dihapus karena sudah dipakai dalam data Transaksi atau Stock Opname!
                  </div>";
        } else {
            // JIKA AMAN, LANJUTKAN PENGHAPUSAN
            $sqlDel = "DELETE FROM ITEMS WHERE ITEM_CODE = ?";
            $stmtDel = sqlsrv_query($conn, $sqlDel, array($idToDelete));
            
            if ($stmtDel) {
                echo "<script>alert('Barang {$idToDelete} Berhasil Dihapus secara permanen!'); window.location.href='?page=master';</script>";
            } else {
                // Tampilkan error DB ke layar html agar Javascript tidak rusak
                $err = htmlspecialchars(print_r(sqlsrv_errors(), true));
                echo "<div class='alert alert-danger mx-3 mt-3'><b>Gagal Hapus (Database Error):</b><br><pre>{$err}</pre></div>";
            }
        }
    }
}

// B. SIMPAN DATA (BARU / UPDATE)
if (isset($_POST['btnSimpan']) || isset($_POST['btnUpdate'])) {
    $isUpdate = isset($_POST['btnUpdate']);
    
    // Ambil Data Form
    $code = $_POST['ITEM_CODE'];
    $name = $_POST['ITEM_NAME'];
    $unit = $_POST['ITEM_UNIT'];
    $cost = $_POST['ITEM_COST'];
    $curr = $_POST['ITEM_CUR'];
    $itty = $_POST['ITTY_CODE'];
    
    // Checkbox
    $inactive = isset($_POST['ITEM_INACTIVE']) ? 1 : 0;
    $forsale  = isset($_POST['ITEM_FORSALE']) ? 1 : 0;
    $inv      = isset($_POST['ITEM_INV']) ? 1 : 0;

    if (empty($code) || empty($name)) {
        echo "<div class='alert alert-warning'>Kode dan Nama Barang wajib diisi!</div>";
    } else {
        if ($isUpdate) {
            // --- UPDATE ---
            $sql = "UPDATE ITEMS SET ITEM_NAME=?, ITEM_UNIT=?, ITEM_COST=?, ITEM_CUR=?, ITTY_CODE=?, 
                    ITEM_INACTIVE=?, ITEM_FORSALE=?, ITEM_INV=? 
                    WHERE ITEM_CODE=?";
            $params = array($name, $unit, $cost, $curr, $itty, $inactive, $forsale, $inv, $code);
            $msg = "Data Barang Berhasil Diupdate!";
        } else {
            // --- INSERT ---
            // Cek Duplikat Kode
            $cek = sqlsrv_query($conn, "SELECT ITEM_CODE FROM ITEMS WHERE ITEM_CODE=?", array($code));
            if(sqlsrv_has_rows($cek)){
                echo "<script>alert('Gagal! Kode Barang $code sudah ada.');</script>";
                $params = null; // Batal
            } else {
                $sql = "INSERT INTO ITEMS (ITEM_NAME, ITEM_UNIT, ITEM_COST, ITEM_CUR, ITTY_CODE, 
                        ITEM_INACTIVE, ITEM_FORSALE, ITEM_INV, ITEM_CODE) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = array($name, $unit, $cost, $curr, $itty, $inactive, $forsale, $inv, $code);
                $msg = "Data Barang Baru Berhasil Disimpan!";
            }
        }

        if ($params) {
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt) {
                echo "<script>alert('$msg'); window.location.href='?page=master&id=$code';</script>";
            } else {
                echo "<div class='alert alert-danger'>Error Database: " . print_r(sqlsrv_errors(), true) . "</div>";
            }
        }
    }
}

// ==================================================================================
// BAGIAN 2: LOGIKA NAVIGASI & VIEW (SUDAH DIPERBAIKI)
// ==================================================================================

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'view';
$currentID = isset($_GET['id']) ? $_GET['id'] : null;
$isEntry = ($mode == 'new' || $mode == 'edit');

// Default Data (Kosong)
$data = [
    'ITEM_CODE'=>'', 'ITEM_NAME'=>'', 'ITEM_UNIT'=>'Pcs', 'ITEM_COST'=>0, 'ITEM_CUR'=>'IDR',
    'ITTY_CODE'=>'RM', 'ITEM_INACTIVE'=>0, 'ITEM_FORSALE'=>1, 'ITEM_INV'=>1, 'ITEM_ONHAND'=>0
];

// Jika Mode View/Edit, Ambil Data dari DB
if ($mode != 'new') {
    // FIX: Jika tidak ada ID, ambil barang pertama (ABAIKAN KODE BARANG YANG KOSONG)
    if (empty($currentID)) {
        $qFirst = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE IS NOT NULL AND ITEM_CODE <> '' ORDER BY ITEM_CODE ASC");
        if ($rFirst = sqlsrv_fetch_array($qFirst)) {
            $currentID = trim($rFirst['ITEM_CODE']);
        }
    }

    // FIX: Gunakan pengecekan ketat agar string kosong tidak membatalkan query
    if ($currentID !== null && $currentID !== '') {
        $qData = sqlsrv_query($conn, "SELECT * FROM ITEMS WHERE ITEM_CODE = ?", array($currentID));
        if ($rData = sqlsrv_fetch_array($qData, SQLSRV_FETCH_ASSOC)) {
            $data = $rData;
        }
    }
}

// Navigasi Next/Prev (FIX: Abaikan data kosong di database)
$prevID = $nextID = $firstID = $lastID = null;
if (!$isEntry && $currentID !== null && $currentID !== '') {
    $qP = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE < ? AND ITEM_CODE <> '' ORDER BY ITEM_CODE DESC", array($currentID)); 
    if($r=sqlsrv_fetch_array($qP)) $prevID=$r['ITEM_CODE'];
    
    $qN = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE > ? AND ITEM_CODE <> '' ORDER BY ITEM_CODE ASC", array($currentID)); 
    if($r=sqlsrv_fetch_array($qN)) $nextID=$r['ITEM_CODE'];
    
    $qF = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE <> '' ORDER BY ITEM_CODE ASC"); 
    if($r=sqlsrv_fetch_array($qF)) $firstID=$r['ITEM_CODE'];
    
    $qL = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE <> '' ORDER BY ITEM_CODE DESC"); 
    if($r=sqlsrv_fetch_array($qL)) $lastID=$r['ITEM_CODE'];
}

// Data Dropdown Tipe Barang
$optItty = "";
$qItty = sqlsrv_query($conn, "SELECT ITTY_CODE, ITTY_DESC FROM ITTY ORDER BY ITTY_CODE ASC");
while($r=sqlsrv_fetch_array($qItty)) {
    $sel = ($data['ITTY_CODE'] == $r['ITTY_CODE']) ? 'selected' : '';
    $optItty .= "<option value='{$r['ITTY_CODE']}' $sel>{$r['ITTY_CODE']} - {$r['ITTY_DESC']}</option>";
}


// Data Dropdown Tipe Barang
$optItty = "";
$qItty = sqlsrv_query($conn, "SELECT ITTY_CODE, ITTY_DESC FROM ITTY ORDER BY ITTY_CODE ASC");
while($r=sqlsrv_fetch_array($qItty)) {
    $sel = ($data['ITTY_CODE'] == $r['ITTY_CODE']) ? 'selected' : '';
    $optItty .= "<option value='{$r['ITTY_CODE']}' $sel>{$r['ITTY_CODE']} - {$r['ITTY_DESC']}</option>";
}
?>

<form method="POST" action="">
    <input type="hidden" name="hapus_id" value="<?php echo $currentID; ?>">

    <div class="row">
        <div class="col-md-9">
            
            <div class="card mb-2 bg-light border">
                <div class="card-body p-2 d-flex justify-content-between align-items-center">
                    <div class="btn-group btn-group-sm">
                        <a href="?page=master&id=<?php echo $firstID; ?>" class="btn btn-outline-secondary <?php echo (!$firstID || $isEntry)?'disabled':''; ?>"><i class="bi bi-skip-backward-fill"></i></a>
                        <a href="?page=master&id=<?php echo $prevID; ?>" class="btn btn-outline-secondary <?php echo (!$prevID || $isEntry)?'disabled':''; ?>"><i class="bi bi-caret-left-fill"></i></a>
                        <button type="button" class="btn btn-outline-secondary disabled fw-bold px-3" style="min-width: 150px;">
                            <?php echo $isEntry ? ($mode=='new'?'INPUT BARU':'EDIT DATA') : $data['ITEM_CODE']; ?>
                        </button>
                        <a href="?page=master&id=<?php echo $nextID; ?>" class="btn btn-outline-secondary <?php echo (!$nextID || $isEntry)?'disabled':''; ?>"><i class="bi bi-caret-right-fill"></i></a>
                        <a href="?page=master&id=<?php echo $lastID; ?>" class="btn btn-outline-secondary <?php echo (!$lastID || $isEntry)?'disabled':''; ?>"><i class="bi bi-skip-forward-fill"></i></a>
                    </div>

                    <div class="btn-group btn-group-sm">
                        <a href="?page=master&mode=new" class="btn btn-success fw-bold <?php echo ($mode=='new')?'active':''; ?>">
                            <i class="bi bi-plus-lg"></i> BARU
                        </a>
                        <a href="?page=master&mode=edit&id=<?php echo $currentID; ?>" class="btn btn-warning fw-bold <?php echo ($mode=='edit')?'active':''; ?> <?php echo (!$currentID || $isEntry)?'disabled':''; ?>">
                            <i class="bi bi-pencil-square"></i> EDIT
                        </a>
                        <?php if (!$isEntry && $currentID): ?>
                            <button type="submit" name="btnHapus" class="btn btn-danger fw-bold" onclick="return confirm('Hapus Barang <?php echo $currentID; ?>?');"><i class="bi bi-dash-lg"></i> HAPUS</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary disabled"><i class="bi bi-dash-lg"></i> HAPUS</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if(!$isEntry): ?>
            <div class="card mb-2">
                <div class="card-body p-2 bg-white d-flex align-items-center">
                    <label class="small fw-bold me-2">Cari / Lompat ke:</label>
                    <select id="jumpToItem" class="form-select form-select-sm select2-ajax" style="width: 100%;"></select>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm" style="background-color: <?php echo $isEntry ? '#fff3cd' : '#ffffff'; ?>;">
                <div class="card-header fw-bold py-2 <?php echo $isEntry ? 'bg-warning text-dark' : 'bg-primary text-white'; ?>">
                    <i class="bi bi-box-seam"></i> INFORMASI BARANG <?php echo $isEntry ? '(MODE EDIT)' : '(READ ONLY)'; ?>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Kode Barang</label>
                            <input type="text" class="form-control fw-bold text-primary" name="ITEM_CODE" 
                                   value="<?php echo $data['ITEM_CODE']; ?>" 
                                   <?php echo ($mode!='new') ? 'readonly' : ''; ?> required maxlength="8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nama Barang</label>
                            <input type="text" class="form-control" name="ITEM_NAME" 
                                   value="<?php echo $data['ITEM_NAME']; ?>" 
                                   <?php echo !$isEntry ? 'readonly' : ''; ?> required>
                        </div>
                        <div class="col-md-3">
                             <label class="form-label small fw-bold">Stok Saat Ini (On Hand)</label>
                             <input type="text" class="form-control bg-light fw-bold text-end" value="<?php echo number_format($data['ITEM_ONHAND'], 2); ?>" readonly>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Tipe Barang</label>
                            <select class="form-select" name="ITTY_CODE" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <?php echo $optItty; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Satuan (Unit)</label>
                            <select class="form-select" name="ITEM_UNIT" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <option value="Pcs" <?php echo ($data['ITEM_UNIT']=='Pcs')?'selected':''; ?>>Pcs</option>
                                <option value="Kg" <?php echo ($data['ITEM_UNIT']=='Kg')?'selected':''; ?>>Kg</option>
                                <option value="Ltr" <?php echo ($data['ITEM_UNIT']=='Ltr')?'selected':''; ?>>Ltr</option>
                                <option value="Set" <?php echo ($data['ITEM_UNIT']=='Set')?'selected':''; ?>>Set</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Cost</label>
                            <input type="number" step="0.001" class="form-control text-end" name="ITEM_COST" 
                                   value="<?php echo $data['ITEM_COST']; ?>" <?php echo !$isEntry ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Mata Uang</label>
                            <select class="form-select" name="ITEM_CUR" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <option value="IDR" <?php echo ($data['ITEM_CUR']=='IDR')?'selected':''; ?>>IDR (Rupiah)</option>
                                <option value="USD" <?php echo ($data['ITEM_CUR']=='USD')?'selected':''; ?>>USD (Dollar)</option>
                            </select>
                        </div>

                        <div class="col-md-12 border-top pt-3 mt-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="ITEM_INV" value="1" id="chkInv"
                                       <?php echo ($data['ITEM_INV']==1)?'checked':''; ?> <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <label class="form-check-label fw-bold" for="chkInv">INV (Inventory)</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="ITEM_FORSALE" value="1" id="chkSale"
                                       <?php echo ($data['ITEM_FORSALE']==1)?'checked':''; ?> <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <label class="form-check-label fw-bold" for="chkSale">SALE (For Sale)</label>
                            </div>
                            <div class="form-check form-check-inline ms-4">
                                <input class="form-check-input" type="checkbox" name="ITEM_INACTIVE" value="1" id="chkActive"
                                       <?php echo ($data['ITEM_INACTIVE']==1)?'checked':''; ?> <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <label class="form-check-label fw-bold text-danger" for="chkActive">NOT ACTIVE</label>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <div class="col-md-3">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-dark text-white text-center fw-bold py-2">STATUS AKSI</div>
                <div class="card-body d-grid gap-2">
                    <?php if ($isEntry): ?>
                        <?php if ($mode == 'edit'): ?>
                            <button type="submit" name="btnUpdate" class="btn btn-warning fw-bold" onclick="return confirm('Update data barang ini?');">
                                <i class="bi bi-save"></i> UPDATE (SIMPAN)
                            </button>
                        <?php else: ?>
                            <button type="submit" name="btnSimpan" class="btn btn-success fw-bold" onclick="return confirm('Simpan barang baru?');">
                                <i class="bi bi-save"></i> SIMPAN BARU
                            </button>
                        <?php endif; ?>
                        
                        <a href="?page=master&id=<?php echo $currentID; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> BATAL
                        </a>
                    <?php else: ?>
                        <div class="alert alert-info text-center small mb-0">
                            <i class="bi bi-eye"></i> Mode Lihat Data<br>
                            Gunakan tombol navigasi di kiri atas untuk pindah barang.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-light fw-bold small py-1 text-center">STATISTIK</div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span>Total Barang:</span>
                        <?php 
                        $qT = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM ITEMS"); 
                        $rT = sqlsrv_fetch_array($qT);
                        echo "<b>{$rT['c']}</b>";
                        ?>
                    </div>
                     <div class="d-flex justify-content-between">
                        <span>Barang Aktif:</span>
                        <?php 
                        $qA = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM ITEMS WHERE ITEM_INACTIVE=0"); 
                        $rA = sqlsrv_fetch_array($qA);
                        echo "<b class='text-success'>{$rA['c']}</b>";
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
$(document).ready(function() {
    // Fitur Cari Barang (Langsung lompat ke ID yang dipilih)
    $('#jumpToItem').on('select2:select', function (e) {
        var data = e.params.data;
        window.location.href = '?page=master&id=' + data.id;
    });
});
</script>