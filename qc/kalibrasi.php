<?php
/**
 * Modul: CRUD Master Alat Ukur Lengkap & Forecasting Kalibrasi
 * Kompatibilitas: PHP 5.4 & SQL Server 2008 (sqlsrv)
 * Fitur: Hybrid Dropdown Grup, Filter Expired, Input Histori Aktual, Edit Aktual, Keamanan Anti-Injection
 */

require_once __DIR__ . '/../config/database.php';

if (!isset($conn) || $conn === false) {
    die("Error: Koneksi database tidak tersedia.");
}

$pesan = '';
$pageRoute = isset($_GET['page']) ? htmlspecialchars($_GET['page'], ENT_QUOTES, 'UTF-8') : 'kalibrasi';
$filter    = isset($_GET['filter']) ? htmlspecialchars($_GET['filter'], ENT_QUOTES, 'UTF-8') : '';

// =========================================================================
// 1. LOGIKA CRUD (SIMPAN AKTUAL, SIMPAN MASTER, EDIT)
// =========================================================================

// A. Logika SIMPAN AKTUAL KALIBRASI (RIWAYAT BARU DARI TOMBOL KALENDER)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_aktual_submitted'])) {
    $alatID_aktual = (int)$_POST['AlatID'];
    $tglAktual     = trim($_POST['TglAktual']);
    $noSertifikat  = trim($_POST['NoSertifikat']);
    $tahunHistori  = !empty($tglAktual) ? date('Y', strtotime($tglAktual)) : date('Y');
    
    if (!empty($tglAktual) && $alatID_aktual > 0) {
        $sqlRiwayat = "INSERT INTO RiwayatKalibrasi (AlatID, Tahun, TglActual, NoSertifikat) VALUES (?, ?, ?, ?)";
        $stmtRiwayat = sqlsrv_query($conn, $sqlRiwayat, array($alatID_aktual, $tahunHistori, $tglAktual, $noSertifikat));
        
        if ($stmtRiwayat) {
            $pesan = "<div class='alert alert-success'>Tanggal Aktual Kalibrasi berhasil ditambahkan! Jadwal (Plan) otomatis diperbarui.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan data aktual: " . print_r(sqlsrv_errors(), true) . "</div>";
        }
    }
}

// B. Logika SIMPAN MASTER ALAT (INSERT / UPDATE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_submitted'])) {
    $alatID       = !empty($_POST['AlatID']) ? (int)$_POST['AlatID'] : 0;
    $noUrut       = trim($_POST['NoUrut']);
    
    $grupSelect   = isset($_POST['GrupAlatSelect']) ? trim($_POST['GrupAlatSelect']) : '';
    $grupBaru     = isset($_POST['GrupAlatBaru']) ? trim($_POST['GrupAlatBaru']) : '';
    $grupAlat     = ($grupSelect === 'BARU') ? $grupBaru : $grupSelect;
    
    $namaAlat     = trim($_POST['NamaAlat']);
    $seriNo       = trim($_POST['SeriNo']);
    $seriIMC      = trim($_POST['SeriNoIMC']);
    $model        = trim($_POST['Model']);
    $rangeUkur    = trim($_POST['RangeUkur']);
    $resolution   = trim($_POST['Resolution']);
    $satuan       = trim($_POST['Satuan']);
    $brand        = trim($_POST['Brand']);
    $supplier     = trim($_POST['Supplier']);
    $tipeKalib    = trim($_POST['TipeKalibrasi']);
    $periode      = !empty($_POST['PeriodeTahun']) ? (int)$_POST['PeriodeTahun'] : 1; 
    $lokasi       = trim($_POST['Lokasi']);
    $factory      = trim($_POST['Factory']);
    $statusAlat   = trim($_POST['StatusAlat']);
    $remark       = trim($_POST['Remark']);
    
    // Variabel Tanggal Aktual Kalibrasi (Dipakai untuk Insert & Update)
    $tglAktual_baru = !empty($_POST['TglAktual']) ? trim($_POST['TglAktual']) : null;
    $noSert_baru    = isset($_POST['NoSertifikat']) ? trim($_POST['NoSertifikat']) : '';
    $tahunHist_baru = !empty($tglAktual_baru) ? date('Y', strtotime($tglAktual_baru)) : date('Y');
    $riwayatID      = !empty($_POST['RiwayatID']) ? (int)$_POST['RiwayatID'] : 0;

    if ($alatID > 0) {
        // UPDATE EKSISTING DENGAN TRANSACTION
        if (sqlsrv_begin_transaction($conn) === false) {
             die(print_r(sqlsrv_errors(), true));
        }

        $sqlUpdate = "UPDATE MasterAlatUkur 
                      SET NoUrut=?, GrupAlat=?, NamaAlat=?, SeriNo=?, SeriNoIMC=?, Model=?, RangeUkur=?, Resolution=?, Satuan=?, Brand=?, Supplier=?, TipeKalibrasi=?, PeriodeBulan=?, Lokasi=?, Factory=?, StatusAlat=?, Remark=? 
                      WHERE AlatID=?";
        $paramsUpdate = array($noUrut, $grupAlat, $namaAlat, $seriNo, $seriIMC, $model, $rangeUkur, $resolution, $satuan, $brand, $supplier, $tipeKalib, $periode, $lokasi, $factory, $statusAlat, $remark, $alatID);
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

        $riwayatSuccess = true;
        if ($tglAktual_baru) {
            if ($riwayatID > 0) {
                // Update histori terakhir jika sudah ada
                $sqlRiwayat = "UPDATE RiwayatKalibrasi SET TglActual=?, NoSertifikat=?, Tahun=? WHERE RiwayatID=?";
                $paramsRiwayat = array($tglAktual_baru, $noSert_baru, $tahunHist_baru, $riwayatID);
            } else {
                // Insert histori baru jika alat belum punya histori sama sekali
                $sqlRiwayat = "INSERT INTO RiwayatKalibrasi (AlatID, Tahun, TglActual, NoSertifikat) VALUES (?, ?, ?, ?)";
                $paramsRiwayat = array($alatID, $tahunHist_baru, $tglAktual_baru, $noSert_baru);
            }
            $stmtRiwayat = sqlsrv_query($conn, $sqlRiwayat, $paramsRiwayat);
            if (!$stmtRiwayat) {
                $riwayatSuccess = false;
            }
        }

        if ($stmtUpdate && $riwayatSuccess) {
            sqlsrv_commit($conn);
            $pesan = "<div class='alert alert-success'>Data Master dan Kalibrasi berhasil diperbarui!</div>";
        } else {
            sqlsrv_rollback($conn);
            $pesan = "<div class='alert alert-danger'>Gagal update: " . print_r(sqlsrv_errors(), true) . "</div>";
        }

    } else {
        // INSERT BARU DENGAN TRANSACTION
        if (sqlsrv_begin_transaction($conn) === false) {
             die(print_r(sqlsrv_errors(), true));
        }

        $sqlInsert = "INSERT INTO MasterAlatUkur (NoUrut, GrupAlat, NamaAlat, SeriNo, SeriNoIMC, Model, RangeUkur, Resolution, Satuan, Brand, Supplier, TipeKalibrasi, PeriodeBulan, Lokasi, Factory, StatusAlat, Remark) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
                      SELECT SCOPE_IDENTITY() AS NewID;";
        $paramsInsert = array($noUrut, $grupAlat, $namaAlat, $seriNo, $seriIMC, $model, $rangeUkur, $resolution, $satuan, $brand, $supplier, $tipeKalib, $periode, $lokasi, $factory, $statusAlat, $remark);
        $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

        if ($stmtInsert) {
            sqlsrv_next_result($stmtInsert);
            sqlsrv_fetch($stmtInsert);
            $newAlatID = sqlsrv_get_field($stmtInsert, 0);

            if ($tglAktual_baru) {
                $sqlRiwayat = "INSERT INTO RiwayatKalibrasi (AlatID, Tahun, TglActual, NoSertifikat) VALUES (?, ?, ?, ?)";
                $paramsRiwayat = array($newAlatID, $tahunHist_baru, $tglAktual_baru, $noSert_baru);
                $stmtRiwayat = sqlsrv_query($conn, $sqlRiwayat, $paramsRiwayat);
                
                if (!$stmtRiwayat) {
                    sqlsrv_rollback($conn);
                    die("Gagal simpan riwayat: " . print_r(sqlsrv_errors(), true));
                }
            }
            sqlsrv_commit($conn);
            $pesan = "<div class='alert alert-success'>Data Alat Baru berhasil disimpan.</div>";
        } else {
            sqlsrv_rollback($conn);
            $pesan = "<div class='alert alert-danger'>Gagal simpan master alat ukur.</div>";
        }
    }
}

// C. AMBIL DATA UNTUK FORM EDIT (MASTER + AKTUAL TERAKHIR)
$editData = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && !empty($_GET['id'])) {
    $sqlEdit = "
        SELECT m.*, h.RiwayatID, h.TglActual, h.NoSertifikat 
        FROM MasterAlatUkur m
        LEFT JOIN (
            SELECT AlatID, RiwayatID, TglActual, NoSertifikat,
                   ROW_NUMBER() OVER (PARTITION BY AlatID ORDER BY TglActual DESC) as rn
            FROM RiwayatKalibrasi
            WHERE TglActual IS NOT NULL
        ) h ON m.AlatID = h.AlatID AND h.rn = 1
        WHERE m.AlatID = ?
    ";
    $stmtEdit = sqlsrv_query($conn, $sqlEdit, array((int)$_GET['id']));
    if ($stmtEdit) {
        $editData = sqlsrv_fetch_array($stmtEdit, SQLSRV_FETCH_ASSOC);
        // Format object DateTime ke string Y-m-d untuk input type="date"
        $editData['TglActualFmt'] = ($editData['TglActual'] instanceof DateTime) ? $editData['TglActual']->format('Y-m-d') : '';
    }
}

// D. AMBIL DATA UNTUK FORM INPUT AKTUAL KALIBRASI
$dataAktual = null;
if (isset($_GET['action']) && $_GET['action'] == 'input_aktual' && !empty($_GET['id'])) {
    $stmtAktual = sqlsrv_query($conn, "SELECT AlatID, NamaAlat, SeriNoIMC FROM MasterAlatUkur WHERE AlatID = ?", array((int)$_GET['id']));
    if ($stmtAktual) {
        $dataAktual = sqlsrv_fetch_array($stmtAktual, SQLSRV_FETCH_ASSOC);
    }
}

// =========================================================================
// 2. QUERY MENGAMBIL DAFTAR GRUP ALAT (DISTINCT)
// =========================================================================
$sqlGrup = "SELECT DISTINCT GrupAlat FROM MasterAlatUkur WHERE GrupAlat IS NOT NULL AND LTRIM(RTRIM(GrupAlat)) <> '' ORDER BY GrupAlat ASC";
$stmtGrup = sqlsrv_query($conn, $sqlGrup);
$listGrup = array();
if ($stmtGrup) {
    while ($r = sqlsrv_fetch_array($stmtGrup, SQLSRV_FETCH_ASSOC)) {
        $listGrup[] = $r['GrupAlat'];
    }
}

// =========================================================================
// 3. QUERY UTAMA: MENAMPILKAN DATA & FORECASTING TANGGAL (SQL SERVER 2008)
// =========================================================================
$tsql = "
WITH HistoriTerakhir AS (
    SELECT 
        AlatID, TglActual, NoSertifikat,
        ROW_NUMBER() OVER (PARTITION BY AlatID ORDER BY TglActual DESC) as RowNum
    FROM RiwayatKalibrasi
    WHERE TglActual IS NOT NULL
)
SELECT 
    m.AlatID, m.NoUrut, m.GrupAlat, m.NamaAlat, m.SeriNoIMC, m.Lokasi, m.Factory, m.StatusAlat,
    m.PeriodeBulan AS PeriodeTahun, 
    h.TglActual AS KalibrasiTerakhir,
    h.NoSertifikat,
    DATEADD(year, m.PeriodeBulan, h.TglActual) AS NextPlan,
    DATEDIFF(day, GETDATE(), DATEADD(year, m.PeriodeBulan, h.TglActual)) AS SisaHari
FROM MasterAlatUkur m
LEFT JOIN HistoriTerakhir h ON m.AlatID = h.AlatID AND h.RowNum = 1
ORDER BY ISNULL(m.GrupAlat, 'Z_Lainnya') ASC, LEN(m.NoUrut) ASC, m.NoUrut ASC
";

$stmt = sqlsrv_query($conn, $tsql);
if ($stmt === false) die("Error Query Database: " . print_r(sqlsrv_errors(), true));

$dataAlat = array();
$countWarning = 0;
$countOverdue = 0;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $sisaHari = $row['SisaHari'];
    if ($row['StatusAlat'] != 'Dipakai') {
        $statusPlan = array('label' => 'Non-Aktif/Rusak', 'class' => 'label-default');
    } elseif ($sisaHari === null) {
        $statusPlan = array('label' => 'Belum Ada Histori', 'class' => 'label-default');
    } elseif ($sisaHari < 0) {
        $statusPlan = array('label' => 'Terlambat (' . abs($sisaHari) . ' Hari)', 'class' => 'label-danger');
        $countOverdue++;
    } elseif ($sisaHari <= 30) {
        $statusPlan = array('label' => 'Mendekati (' . $sisaHari . ' Hari)', 'class' => 'label-warning');
        $countWarning++;
    } else {
        $statusPlan = array('label' => 'Aman (' . $sisaHari . ' Hari)', 'class' => 'label-success');
    }

    $row['StatusPlan'] = $statusPlan;
    
    $tampilData = true;
    if ($filter === 'overdue' && !($row['StatusAlat'] == 'Dipakai' && $sisaHari !== null && $sisaHari < 0)) $tampilData = false;
    elseif ($filter === 'warning' && !($row['StatusAlat'] == 'Dipakai' && $sisaHari !== null && $sisaHari >= 0 && $sisaHari <= 30)) $tampilData = false;

    if ($tampilData) $dataAlat[] = $row;
}
?>

<!-- ======================= FRONT-END HTML & CSS ======================= -->
<style>
    .kalibrasi-wrapper { font-family: Arial, sans-serif; font-size: 13px; color: #333; background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
    .kalibrasi-wrapper h2 { color: #2c3e50; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; margin-top:0; }
    .kalibrasi-wrapper .alert { padding: 12px; margin-bottom: 15px; border-radius: 4px; font-weight: bold; border: 1px solid transparent; }
    .kalibrasi-wrapper .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .kalibrasi-wrapper .alert-danger { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    .kalibrasi-wrapper .alert-warning { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
    .kalibrasi-wrapper .form-panel { background-color: #fdfdfd; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
    .kalibrasi-wrapper .form-group { display: inline-block; width: 31%; margin-bottom: 12px; margin-right: 2%; vertical-align: top; }
    .kalibrasi-wrapper .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; font-size: 12px; }
    .kalibrasi-wrapper .form-group input, .kalibrasi-wrapper .form-group select, .kalibrasi-wrapper .form-group textarea { width: 100%; padding: 7px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
    .kalibrasi-wrapper .table-erp { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .kalibrasi-wrapper .table-erp th { background-color: #2c3e50; color: #fff; padding: 10px; border: 1px solid #bdc3c7; text-align: center; }
    .kalibrasi-wrapper .table-erp td { padding: 8px; border: 1px solid #bdc3c7; text-align: center; vertical-align: middle; }
    .kalibrasi-wrapper .table-erp tr:hover { background-color: #f1f8ff; }
    .kalibrasi-wrapper .group-header { background-color: #d1d8e0 !important; color: #2c3e50; font-weight: bold; font-size: 14px; text-align: left !important; padding-left: 15px !important; }
    .kalibrasi-wrapper .row-danger { background-color: #ffeaea !important; }
    .kalibrasi-wrapper .row-warning { background-color: #fffcf0 !important; }
    .kalibrasi-wrapper .label { padding: 5px 8px; border-radius: 3px; font-size: 11px; color: #fff; font-weight: bold; display: inline-block; }
    .kalibrasi-wrapper .label-success { background-color: #27ae60; }
    .kalibrasi-wrapper .label-warning { background-color: #f39c12; }
    .kalibrasi-wrapper .label-danger  { background-color: #c0392b; }
    .kalibrasi-wrapper .label-default { background-color: #95a5a6; }
    .kalibrasi-wrapper .btn-erp { padding: 6px 10px; background: #3498db; color: #fff; text-decoration: none; border-radius: 3px; border: none; cursor: pointer; font-size: 12px; display: inline-block;}
    .kalibrasi-wrapper .btn-add { background: #27ae60; margin-bottom: 10px; font-size: 14px; font-weight: bold;}
    .kalibrasi-wrapper .btn-edit { background: #f39c12; }
    .kalibrasi-wrapper .btn-erp:hover { opacity: 0.8; }
</style>

<div class="kalibrasi-wrapper">
    <h2>Daftar Induk & Jadwal Kalibrasi Alat Ukur</h2>
    
    <?php echo $pesan; ?>

    <?php if ($countOverdue > 0): ?>
        <div class="alert alert-danger" style="display: flex; justify-content: space-between; align-items: center;">
            <span>⚠️ PERHATIAN: Terdapat <?php echo $countOverdue; ?> Alat Ukur yang TELAH MELEWATI JADWAL KALIBRASI!</span>
            <?php if ($filter !== 'overdue'): ?>
                <a href="?page=<?php echo $pageRoute; ?>&filter=overdue" class="btn-erp" style="background-color: #c0392b; padding: 4px 10px; color: #fff;">Lihat Data Expired</a>
            <?php else: ?>
                <a href="?page=<?php echo $pageRoute; ?>" class="btn-erp" style="background-color: #fff; color: #c0392b; padding: 4px 10px; border: 1px solid #c0392b;">Reset Filter</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($countWarning > 0): ?>
        <div class="alert alert-warning" style="display: flex; justify-content: space-between; align-items: center;">
            <span>🔔 PENGINGAT: Terdapat <?php echo $countWarning; ?> Alat Ukur yang mendekati jadwal kalibrasi (Sisa <= 30 Hari).</span>
            <?php if ($filter !== 'warning'): ?>
                <a href="?page=<?php echo $pageRoute; ?>&filter=warning" class="btn-erp" style="background-color: #d35400; padding: 4px 10px; color: #fff;">Lihat Data Mendekati</a>
            <?php else: ?>
                <a href="?page=<?php echo $pageRoute; ?>" class="btn-erp" style="background-color: #fff; color: #d35400; padding: 4px 10px; border: 1px solid #d35400;">Reset Filter</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div style="text-align: left; margin-bottom: 15px;">
        <button class="btn-erp btn-add" id="btnToggleForm">+ Input Master Alat Baru</button>
    </div>

    <!-- ================= FORM INPUT AKTUAL KALIBRASI (Dari Tombol Kalender) ================= -->
    <?php if ($dataAktual): ?>
    <div class="form-panel" id="formAktual" style="display: block; border-left: 4px solid #27ae60;">
        <h3 style="margin-top:0; color:#27ae60;">📅 Input Hasil Kalibrasi: <?php echo htmlspecialchars($dataAktual['NamaAlat'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($dataAktual['SeriNoIMC'], ENT_QUOTES, 'UTF-8'); ?>)</h3>
        <form action="?page=<?php echo $pageRoute; ?>" method="POST">
            <input type="hidden" name="form_aktual_submitted" value="1">
            <input type="hidden" name="AlatID" value="<?php echo $dataAktual['AlatID']; ?>">
            
            <div class="form-group" style="width: 30%;">
                <label>Tanggal Pelaksanaan (Aktual)</label>
                <input type="date" name="TglAktual" required>
            </div>
            <div class="form-group" style="width: 30%;">
                <label>No. Sertifikat Kalibrasi</label>
                <input type="text" name="NoSertifikat" placeholder="Opsional / Jika Ada">
            </div>
            
            <div style="display: inline-block; vertical-align: bottom; margin-bottom: 12px;">
                <button type="submit" class="btn-erp" style="background:#27ae60; padding: 8px 15px;">Simpan Kalibrasi Baru</button>
                <a href="?page=<?php echo $pageRoute; ?>" class="btn-erp" style="background:#95a5a6; padding: 8px 15px;">Batal</a>
            </div>
        </form>
        <p style="font-size: 11px; color: #7f8c8d; margin-top: 5px; margin-bottom: 0;">*Menyimpan data ini akan otomatis menggeser jadwal (Plan) kalibrasi selanjutnya sesuai periode alat ukur.</p>
    </div>
    <?php endif; ?>

    <!-- ================= FORM CRUD MASTER ALAT ================= -->
    <div class="form-panel" id="formPanel" style="display: <?php echo $editData ? 'block' : 'none'; ?>;">
        <h3 style="margin-top:0; color:#2980b9;"><?php echo $editData ? 'Edit Data Master Alat' : 'Input Data Alat Ukur Baru'; ?></h3>
        
        <form action="?page=<?php echo $pageRoute; ?>&action=save" method="POST">
            <input type="hidden" name="form_submitted" value="1">
            <input type="hidden" name="AlatID" value="<?php echo $editData ? $editData['AlatID'] : ''; ?>">
            
            <div class="form-group">
                <label>No. Urut</label>
                <input type="text" name="NoUrut" required value="<?php echo $editData ? htmlspecialchars($editData['NoUrut'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Grup Alat</label>
                <select name="GrupAlatSelect" id="GrupAlatSelect" required>
                    <option value="">-- Pilih Grup Alat --</option>
                    <?php 
                    $isGrupExist = false;
                    foreach ($listGrup as $grup): 
                        if ($editData && $editData['GrupAlat'] == $grup) {
                            $isGrupExist = true;
                        }
                    ?>
                        <option value="<?php echo htmlspecialchars($grup, ENT_QUOTES, 'UTF-8'); ?>" 
                            <?php echo ($editData && $editData['GrupAlat'] == $grup) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($grup, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                    
                    <?php $isBaru = ($editData && !$isGrupExist && !empty($editData['GrupAlat'])) ? true : false; ?>
                    <option value="BARU" <?php echo $isBaru ? 'selected' : ''; ?>>+ Input Grup Baru...</option>
                </select>
                <input type="text" name="GrupAlatBaru" id="GrupAlatBaru" placeholder="Ketik nama grup baru..." 
                       value="<?php echo $isBaru ? htmlspecialchars($editData['GrupAlat'], ENT_QUOTES, 'UTF-8') : ''; ?>" 
                       style="display: none; margin-top: 5px;">
            </div>

            <div class="form-group">
                <label>Nama Alat Ukur</label>
                <input type="text" name="NamaAlat" required value="<?php echo $editData ? htmlspecialchars($editData['NamaAlat'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Seri No</label>
                <input type="text" name="SeriNo" value="<?php echo $editData ? htmlspecialchars($editData['SeriNo'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Seri No. IMC</label>
                <input type="text" name="SeriNoIMC" required value="<?php echo $editData ? htmlspecialchars($editData['SeriNoIMC'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Model</label>
                <input type="text" name="Model" value="<?php echo $editData ? htmlspecialchars($editData['Model'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Range Ukur</label>
                <input type="text" name="RangeUkur" value="<?php echo $editData ? htmlspecialchars($editData['RangeUkur'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Resolution</label>
                <input type="text" name="Resolution" value="<?php echo $editData ? htmlspecialchars($editData['Resolution'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Satuan</label>
                <input type="text" name="Satuan" value="<?php echo $editData ? htmlspecialchars($editData['Satuan'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Brand</label>
                <input type="text" name="Brand" value="<?php echo $editData ? htmlspecialchars($editData['Brand'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Supplier</label>
                <input type="text" name="Supplier" value="<?php echo $editData ? htmlspecialchars($editData['Supplier'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Tipe Kalibrasi</label>
                <input type="text" name="TipeKalibrasi" value="<?php echo $editData ? htmlspecialchars($editData['TipeKalibrasi'], ENT_QUOTES, 'UTF-8') : 'External'; ?>">
            </div>
            <div class="form-group">
                <label>Periode Kalibrasi (Tahun)</label>
                <input type="number" name="PeriodeTahun" value="<?php echo $editData ? $editData['PeriodeBulan'] : '3'; ?>" required>
            </div>
            <div class="form-group">
                <label>Lokasi</label>
                <input type="text" name="Lokasi" value="<?php echo $editData ? htmlspecialchars($editData['Lokasi'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Factory</label>
                <input type="text" name="Factory" value="<?php echo $editData ? htmlspecialchars($editData['Factory'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Status Alat</label>
                <select name="StatusAlat">
                    <option value="Dipakai" <?php echo ($editData && $editData['StatusAlat']=='Dipakai') ? 'selected' : ''; ?>>Dipakai</option>
                    <option value="Rusak" <?php echo ($editData && $editData['StatusAlat']=='Rusak') ? 'selected' : ''; ?>>Rusak</option>
                    <option value="Tidak Pakai" <?php echo ($editData && $editData['StatusAlat']=='Tidak Pakai') ? 'selected' : ''; ?>>Tidak Pakai</option>
                </select>
            </div>
            <div class="form-group" style="width: 65%;">
                <label>Remark</label>
                <input type="text" name="Remark" value="<?php echo $editData ? htmlspecialchars($editData['Remark'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>

            <!-- Input / Edit Tanggal Aktual Kalibrasi selalu ditampilkan -->
            <div style="border-top: 1px dashed #ccc; padding-top: 10px; margin-top: 10px; width: 100%; display: inline-block;">
                <h4 style="margin-top:0; color:#e67e22;">Input / Edit Tanggal Aktual Kalibrasi Terakhir</h4>
                <!-- Hidden Input untuk membawa ID Histori jika sedang mengedit -->
                <input type="hidden" name="RiwayatID" value="<?php echo $editData && isset($editData['RiwayatID']) ? $editData['RiwayatID'] : ''; ?>">
                <div class="form-group" style="width: 48%;">
                    <label>Tanggal Aktual Kalibrasi (YYYY-MM-DD)</label>
                    <input type="date" name="TglAktual" value="<?php echo $editData ? $editData['TglActualFmt'] : ''; ?>">
                </div>
                <div class="form-group" style="width: 48%;">
                    <label>No. Sertifikat</label>
                    <input type="text" name="NoSertifikat" value="<?php echo $editData ? htmlspecialchars($editData['NoSertifikat'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
            </div>

            <div style="margin-top: 15px; text-align: right; width: 100%; display: inline-block;">
                <?php if ($editData): ?>
                    <a href="?page=<?php echo $pageRoute; ?>" class="btn-erp" style="background:#95a5a6;">Batal Edit</a>
                <?php else: ?>
                    <button type="button" class="btn-erp" id="btnBatal" style="background:#95a5a6;">Batal</button>
                <?php endif; ?>
                <button type="submit" class="btn-erp" style="background:#27ae60;">Simpan Data</button>
            </div>
        </form>
    </div>

    <!-- ================= TABEL DAFTAR ALAT ================= -->
    <table class="table-erp">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Alat</th>
                <th>Seri No. IMC</th>
                <th>Lokasi</th>
                <th>Factory</th>
                <th>Periode</th>
                <th>Kalibrasi Terakhir</th>
                <th>Jadwal Berikutnya (Plan)</th>
                <th>Status Kalibrasi</th>
                <th width="80">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $currentGroup = null; 
            foreach ($dataAlat as $alat): 
                $alatID      = $alat['AlatID'];
                $noUrut      = htmlspecialchars($alat['NoUrut'] ?: '-', ENT_QUOTES, 'UTF-8');
                $grupAlat    = !empty($alat['GrupAlat']) ? htmlspecialchars($alat['GrupAlat'], ENT_QUOTES, 'UTF-8') : 'Tanpa Grup';
                $namaAlat    = htmlspecialchars($alat['NamaAlat'] ?: '-', ENT_QUOTES, 'UTF-8');
                $seriIMC     = htmlspecialchars($alat['SeriNoIMC'] ?: '-', ENT_QUOTES, 'UTF-8');
                $lokasi      = htmlspecialchars($alat['Lokasi'] ?: '-', ENT_QUOTES, 'UTF-8');
                $factory     = htmlspecialchars($alat['Factory'] ?: '-', ENT_QUOTES, 'UTF-8');
                $periode     = htmlspecialchars($alat['PeriodeTahun'] ?: '1', ENT_QUOTES, 'UTF-8') . ' Tahun';
                
                $tglTerakhir = ($alat['KalibrasiTerakhir'] instanceof DateTime) ? $alat['KalibrasiTerakhir']->format('d-M-Y') : '-';
                $tglNextPlan = ($alat['NextPlan'] instanceof DateTime) ? $alat['NextPlan']->format('d-M-Y') : '-';

                $rowColorClass = '';
                if ($alat['StatusAlat'] == 'Dipakai' && $alat['SisaHari'] !== null) {
                    if ($alat['SisaHari'] < 0) $rowColorClass = 'row-danger';
                    elseif ($alat['SisaHari'] <= 30) $rowColorClass = 'row-warning';
                }

                if ($currentGroup !== $grupAlat) {
                    $currentGroup = $grupAlat;
                    echo "<tr><td colspan='10' class='group-header'>🗂️ GRUP: " . $currentGroup . "</td></tr>";
                }
            ?>
            <tr class="<?php echo $rowColorClass; ?>">
                <td><?php echo $noUrut; ?></td>
                <td style="text-align: left; font-weight: bold;"><?php echo $namaAlat; ?></td>
                <td><?php echo $seriIMC; ?></td>
                <td><?php echo $lokasi; ?></td>
                <td><?php echo $factory; ?></td>
                <td style="color: #2980b9; font-weight: bold;"><?php echo $periode; ?></td>
                <td><?php echo $tglTerakhir; ?></td>
                <td style="font-weight: bold;"><?php echo $tglNextPlan; ?></td>
                <td><span class="label <?php echo $alat['StatusPlan']['class']; ?>"><?php echo $alat['StatusPlan']['label']; ?></span></td>
                
                <td style="white-space: nowrap;">
                    <a href="?page=<?php echo $pageRoute; ?>&action=input_aktual&id=<?php echo $alatID; ?>#formAktual" class="btn-erp" style="background-color: #27ae60;" title="Input Tanggal Aktual Kalibrasi Baru">📅</a>
                    <a href="?page=<?php echo $pageRoute; ?>&action=edit&id=<?php echo $alatID; ?>#formPanel" class="btn-erp btn-edit" title="Edit Data Master">✏️</a>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($dataAlat)): ?>
            <tr>
                <td colspan="10" style="text-align: center; padding: 20px;">Belum ada data Alat Ukur di database (atau data tidak ditemukan berdasarkan filter).</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
<script>
    $(document).ready(function(){
        $('#btnToggleForm').click(function(){
            $('#formPanel').slideToggle();
            if(window.location.search.indexOf('action=edit') > -1) {
                window.location.href = "?page=<?php echo $pageRoute; ?>";
            }
        });

        $('#btnBatal').click(function(){
            $('#formPanel').slideUp();
        });

        $('.table-erp tbody tr').click(function(){
            if (!$(this).find('td').hasClass('group-header')) {
                $('.table-erp tbody tr:not(.row-danger):not(.row-warning)').css('background-color', '');
                $(this).css('background-color', '#fff3cd');
            }
        });

        setTimeout(function() {
            $('.alert-success').fadeOut('slow');
        }, 3500);

        function checkGrupAlat() {
            if ($('#GrupAlatSelect').val() === 'BARU') {
                $('#GrupAlatBaru').slideDown().attr('required', true);
            } else {
                $('#GrupAlatBaru').slideUp().removeAttr('required').val('');
            }
        }
        
        checkGrupAlat();
        
        $('#GrupAlatSelect').change(function(){
            checkGrupAlat();
        });
    });
</script>

<?php 
if (isset($stmt) && $stmt) sqlsrv_free_stmt($stmt);
?>