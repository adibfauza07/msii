<?php
require_once __DIR__ . '/../config/database_p1.php';

// ==================================================================================
// BAGIAN 1: PROSES DATA (POST)
// ==================================================================================

// A. HAPUS TRANSAKSI
if (isset($_POST['btnHapusTransaksi'])) {
    $idToDelete = $_POST['hapus_id'];
    if ($idToDelete) {
        sqlsrv_begin_transaction($conn);
        try {
            sqlsrv_query($conn, "DELETE FROM INV_TRAN WHERE TRAN_ID = ?", array($idToDelete));
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

// B. PROSES SIMPAN BARU (INSERT) & UPDATE
if (isset($_POST['btnSimpanTransaksi']) || isset($_POST['btnUpdateTransaksi'])) {
    
    $isUpdate = isset($_POST['btnUpdateTransaksi']);
    $currentID = $_POST['hapus_id'];

$tranDoc  = $_POST['TRAN_DOC'];
    $tranDate = $_POST['TRAN_DATE'];
    $trtyCode = $_POST['TRTY_CODE'];
    $supCode  = $_POST['SUP_CODE'];
    $remark   = $_POST['TRAN_REM'];
    
    // PERBAIKAN: Jika Supplier kosong, ubah jadi NULL agar lolos Foreign Key Database
    if ($supCode === "") {
        $supCode = null;
    }
    
    $items    = isset($_POST['item_code']) ? $_POST['item_code'] : [];
    $qtys     = isset($_POST['item_qty']) ? $_POST['item_qty'] : [];

// Validasi Supplier Dihapus (sekarang boleh kosong)
    if (count($items) == 0) {
        echo "<script>alert('Gagal: Belum ada barang!');</script>";
    } else {
        sqlsrv_begin_transaction($conn);
        try {

                        if (!$isUpdate && $trtyCode != '14') {
                $cekDoc = sqlsrv_query($conn, "SELECT TOP 1 TRAN_DOC FROM TRANS WHERE TRAN_DOC = ?", array($tranDoc));
                if ($cekDoc && sqlsrv_fetch_array($cekDoc)) {
                    throw new Exception("No. Dokumen '{$tranDoc}' sudah dipakai! Silakan ganti No. Dokumen di atas atau klik tombol + BARU.");
                }
            };


            if ($isUpdate) {
                $sqlHead = "UPDATE TRANS SET TRAN_DATE=?, TRTY_CODE=?, SUP_CODE=?, TRAN_REM=? WHERE TRAN_ID=?";
                $paramsHead = array($tranDate, $trtyCode, $supCode, $remark, $currentID);
                if (!sqlsrv_query($conn, $sqlHead, $paramsHead)) throw new Exception("Gagal Update Header");
                
                if (!sqlsrv_query($conn, "DELETE FROM INV_TRAN WHERE TRAN_ID=?", array($currentID))) throw new Exception("Gagal Reset Detail");
                $targetID = $currentID;



            } else {
                $sqlHead = "INSERT INTO TRANS (TRAN_DOC, TRAN_DATE, TRTY_CODE, SUP_CODE, TRAN_REM, TRAN_ADATE) 
                            VALUES (?, ?, ?, ?, ?, GETDATE()); 
                            SELECT SCOPE_IDENTITY() AS ID";
                
                $paramsHead = array($tranDoc, $tranDate, $trtyCode, $supCode, $remark);
                $stmtHead = sqlsrv_query($conn, $sqlHead, $paramsHead);
                
                if ($stmtHead === false) throw new Exception("Gagal Simpan Header: " . print_r(sqlsrv_errors(), true));

                sqlsrv_next_result($stmtHead); 
                $rowID = sqlsrv_fetch_array($stmtHead);
                
                if(!$rowID || !isset($rowID['ID'])) throw new Exception("Gagal mengambil ID Transaksi Baru");
                $targetID = $rowID['ID'];
            }

            $sqlDet = "INSERT INTO INV_TRAN (TRAN_ID, ITEM_ID, ITEM_CODE, IT_QTY, IT_LINENO, TRAN_REMARK, ST_CODE) 
                       VALUES (?, ?, ?, ?, ?, ?, 'OK')";
            
            $lineNo = 1;
            foreach ($items as $index => $code) {
                $qty = $qtys[$index];
                
                $qCek = sqlsrv_query($conn, "SELECT TOP 1 ITEM_ID FROM ITEMS WHERE ITEM_CODE = ?", array($code));
                $rCek = sqlsrv_fetch_array($qCek);
                $itemID = $rCek ? $rCek['ITEM_ID'] : 0;

                $paramsDet = array($targetID, $itemID, $code, $qty, $lineNo, $remark);
                if (!sqlsrv_query($conn, $sqlDet, $paramsDet)) throw new Exception("Gagal Simpan Item $code");
                $lineNo++;
            }

            sqlsrv_commit($conn);
            echo "<script>alert('Data Berhasil Disimpan!'); window.location.href='?page=transaksi&id=$targetID';</script>";
            exit;

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo "<div class='alert alert-danger'><b>System Error:</b> " . $e->getMessage() . "</div>";
        }
    }
}

// ==================================================================================
// BAGIAN 2: LOGIKA FETCH DATA (DENGAN ERROR TRAPPING)
// ==================================================================================

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'view'; 
$currentID = isset($_GET['id']) ? $_GET['id'] : null;
$isEntry = ($mode == 'new' || $mode == 'edit');

$dataHeader = [
    'TRAN_ID' => '', 'TRAN_DOC' => 'AUTO', 'TRAN_DATE' => date('Y-m-d'), 
    'TRTY_CODE' => '', 'SUP_CODE' => '', 'TRAN_REM' => ''
];
$dataDetail = [];

if ($mode == 'new') {
    $dataHeader['TRAN_DOC'] = "TR-" . date('ymd-His'); 
} else {
    // Cari Data Terakhir jika tidak ada ID
    if (!$currentID) {
        $qLast = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID DESC");
        if ($qLast === false) die("<div class='alert alert-danger m-3'><b>Error Query TRANS (Last ID):</b><br>".print_r(sqlsrv_errors(), true)."</div>");
        if ($rLast = sqlsrv_fetch_array($qLast)) $currentID = $rLast['TRAN_ID'];
    }

    // Load Data Berdasarkan ID
    if ($currentID) {
        $qHead = sqlsrv_query($conn, "SELECT * FROM TRANS WHERE TRAN_ID = ?", array($currentID));
        if ($qHead === false) die("<div class='alert alert-danger m-3'><b>Error Query TRANS (Header):</b><br>".print_r(sqlsrv_errors(), true)."</div>");
        
        if ($rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC)) {
            $dataHeader = $rHead;
            if (isset($dataHeader['TRAN_DATE']) && $dataHeader['TRAN_DATE'] instanceof DateTime) {
                $dataHeader['TRAN_DATE'] = $dataHeader['TRAN_DATE']->format('Y-m-d');
            }
        }

        // Ambil Detail
        $sqlDetail = "SELECT T.IT_LINENO, T.IT_QTY, 
                             COALESCE(NULLIF(I.ITEM_CODE, ''), NULLIF(T.ITEM_CODE, ''), '???') as ITEM_CODE,
                             COALESCE(NULLIF(I.ITEM_NAME, ''), NULLIF(T.TRAN_REMARK, ''), '(Barang Tidak Dikenal)') as ITEM_NAME,
                             COALESCE(NULLIF(I.ITEM_UNIT, ''), '-') as ITEM_UNIT
                      FROM INV_TRAN T 
                      LEFT JOIN ITEMS I ON (T.ITEM_ID = I.ITEM_ID OR (T.ITEM_CODE IS NOT NULL AND LTRIM(RTRIM(T.ITEM_CODE)) = LTRIM(RTRIM(I.ITEM_CODE))))
                      WHERE T.TRAN_ID = ? 
                      ORDER BY T.IT_LINENO ASC";

        $qDet = sqlsrv_query($conn, $sqlDetail, array($currentID));
        if ($qDet === false) die("<div class='alert alert-danger m-3'><b>Error Query INV_TRAN (Detail):</b><br>".print_r(sqlsrv_errors(), true)."</div>");
        
        while ($rDet = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) {
            $dataDetail[] = $rDet;
        }
    }
}

// Navigasi (Aman dari Fatal Error)
$prevID = $nextID = $firstID = $lastID = null;
if (!$isEntry && $currentID) {
    $qP = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS WHERE TRAN_ID < ? ORDER BY TRAN_ID DESC", array($currentID)); 
    if($qP && $r=sqlsrv_fetch_array($qP)) $prevID = $r['TRAN_ID'];
    
    $qN = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS WHERE TRAN_ID > ? ORDER BY TRAN_ID ASC", array($currentID)); 
    if($qN && $r=sqlsrv_fetch_array($qN)) $nextID = $r['TRAN_ID'];
    
    $qF = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID ASC"); 
    if($qF && $r=sqlsrv_fetch_array($qF)) $firstID = $r['TRAN_ID'];
    
    $qL = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID DESC"); 
    if($qL && $r=sqlsrv_fetch_array($qL)) $lastID = $r['TRAN_ID'];
}

// Load List Supplier
$optSup = ""; 
$qS = sqlsrv_query($conn, "SELECT SUP_CODE, SUP_COMP FROM SUPPLIER ORDER BY SUP_COMP ASC");
if ($qS === false) die("<div class='alert alert-danger m-3'><b>Error Query SUPPLIER:</b><br>".print_r(sqlsrv_errors(), true)."</div>");
while($r=sqlsrv_fetch_array($qS)) { 
    $s = ($dataHeader['SUP_CODE'] == $r['SUP_CODE']) ? 'selected' : ''; 
    $optSup .= "<option value='{$r['SUP_CODE']}' $s>{$r['SUP_COMP']}</option>"; 
}

// Load List Transaksi Type (TRTY)
$optTrty = ""; 
$qT = sqlsrv_query($conn, "SELECT TRTY_CODE, TRTY_DESC FROM TRTY ORDER BY TRTY_CODE ASC");
if ($qT === false) die("<div class='alert alert-danger m-3'><b>Error Query TRTY:</b><br>".print_r(sqlsrv_errors(), true)."</div>");
while($r=sqlsrv_fetch_array($qT)) { 
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
                            <input type="text" class="form-control form-control-sm fw-bold text-primary" name="TRAN_DOC" 
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
                        <div class="col-6 col-md-3">
                            <label class="small fw-bold">Tanggal</label>
                            <input type="date" class="form-control form-control-sm" name="TRAN_DATE" 
                                   value="<?php echo $dataHeader['TRAN_DATE']; ?>" <?php echo !$isEntry ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-12 col-md-9">
                            <label class="small fw-bold">Keterangan</label>
                            <input type="text" class="form-control form-control-sm" name="TRAN_REM" 
                                   value="<?php echo $dataHeader['TRAN_REM']; ?>" <?php echo !$isEntry ? 'readonly' : ''; ?>>
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
                            <input type="number" id="inputQty" class="form-control form-control-sm" value="" placeholder="0">
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

    // FUNGSI KLIK TOMBOL REKAP ICL
// FUNGSI KLIK TOMBOL REKAP ICL (BEBAS TANGGAL)
    $('#btnTampilIcl').click(function() {
        var start = $('#icl_start_date').val();
        var end   = $('#icl_end_date').val();
        
        // Langsung buka tab baru, meskipun tanggal kosong
        window.open('print_icl_list.php?start_date=' + start + '&end_date=' + end, '_blank');
    });

// 4. TOMBOL TAMBAH BARANG
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
        
        // --- PERUBAHAN ADA DI SINI ---
        $('#inputBarang').val(null).trigger('change'); // Kosongkan dropdown
        $('#inputQty').val(''); // Kembalikan qty ke 1
        
        // TRIK MAGIC: Langsung otomatis fokus dan buka dropdown Cari Barang lagi!
        setTimeout(function() {
            $('#inputBarang').select2('open');
        }, 100); 
    });

    // --- TAMBAHAN BARU: TEKAN ENTER DI KOLOM QTY ---
    // Jadi nggak perlu capek-capek klik tombol "+ Tambah" pakai mouse
    $('#inputQty').on('keypress', function(e) {
        if (e.which == 13) { // 13 adalah kode tombol Enter
            e.preventDefault(); // Cegah form ke-submit secara tidak sengaja
            $('#btnTambahRow').click(); // Jalankan fungsi tombol tambah
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
});
</script>