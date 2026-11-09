<?php
// FILE: input_spc.php

require_once __DIR__ . '/../config/global.php';

if ($conn === false) {
    die("<div class='alert alert-danger p-3 m-3 font-weight-bold'>Koneksi database tidak tersedia. Pastikan Anda sudah login.</div>");
}

$pesan = "";
$active_plant_session = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';
$nama_plant = ($active_plant_session == 'p2') ? 'PLANT 2' : 'PLANT 1';
$badge_color = ($active_plant_session == 'p2') ? 'bg-success' : 'bg-primary';

$search_code = isset($_GET['search_code']) ? trim($_GET['search_code']) : '';

// ==========================================
// 1. PROSES HAPUS DATA
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'delete' && !empty($_GET['code'])) {
    $code_to_delete = trim($_GET['code']);
    sqlsrv_begin_transaction($conn);
    try {
        sqlsrv_query($conn, "SET ARITHABORT ON; SET ANSI_WARNINGS ON;");
        $sqlGetId = "SELECT SpcID FROM SPC_Inspection_Header WHERE SpcCode = ?";
        $stmtGetId = sqlsrv_query($conn, $sqlGetId, array($code_to_delete));
        
        if ($stmtGetId && $rowId = sqlsrv_fetch_array($stmtGetId, SQLSRV_FETCH_ASSOC)) {
            $id_target = $rowId['SpcID'];
            
            $sqlDelDetail = "DELETE FROM SPC_Inspection_Detail WHERE SpcID = ?";
            $stmtDelD = sqlsrv_query($conn, $sqlDelDetail, array($id_target));
            if ($stmtDelD === false) {
                $err = sqlsrv_errors();
                throw new Exception("Gagal menghapus Detail: " . (isset($err[0]['message']) ? $err[0]['message'] : 'Unknown'));
            }
            
            $sqlDelHeader = "DELETE FROM SPC_Inspection_Header WHERE SpcID = ?";
            $stmtDelH = sqlsrv_query($conn, $sqlDelHeader, array($id_target));
            if ($stmtDelH === false) {
                $err = sqlsrv_errors();
                throw new Exception("Gagal menghapus Header: " . (isset($err[0]['message']) ? $err[0]['message'] : 'Unknown'));
            }
            
            sqlsrv_commit($conn);
            echo "<script>alert('Data SPC " . $code_to_delete . " dihapus.'); window.location.href='dashboard_qc.php?page=spc';</script>";
            exit();
        } else {
            throw new Exception("Data dokumen dengan kode '$code_to_delete' tidak ditemukan.");
        }
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $pesan = "<div class='alert alert-danger'><strong>Gagal Hapus:</strong> " . $e->getMessage() . "</div>";
    }
}

// ==========================================
// 2. PREDIKSI KODE SPC BARU
// ==========================================
$date_prefix = date('my'); 
$prefix_code = "SPC" . $date_prefix;
$next_spc_code = "";

$sqlMax = "SELECT MAX(SpcCode) as maxCode FROM SPC_Inspection_Header WHERE SpcCode LIKE ?";
$stmtMax = sqlsrv_query($conn, $sqlMax, array($prefix_code . '%'));
if ($stmtMax && $rowMax = sqlsrv_fetch_array($stmtMax, SQLSRV_FETCH_ASSOC)) {
    if (empty($rowMax['maxCode'])) {
        $next_spc_code = $prefix_code . "00001";
    } else {
        $lastNum = (int)substr($rowMax['maxCode'], -5);
        $next_spc_code = $prefix_code . str_pad($lastNum + 1, 5, "0", STR_PAD_LEFT);
    }
} else {
    $next_spc_code = $prefix_code . "00001";
}

// ==========================================
// 3. PROSES PENCARIAN (MODE EDIT & REPORT)
// ==========================================
$edit_mode = false;
$spc_code_edit = ""; 
$spc_id_edit = "";   
$header_edit = [
    'PartNo' => '', 'PartName' => '', 'Customer' => '', 'Periode' => '',
    'Dimensi' => '', 'Cavity' => '', 'Inspector' => ''
];

$detail_edit = [];
for ($i = 1; $i <= 3; $i++) {
    $detail_edit[$i] = array_fill(1, 31, '');
}
$label_tgl_arr = array_fill(1, 31, ''); 

$chart_labels = [];
$chart_data_s1 = [];
$chart_data_s2 = [];
$chart_data_s3 = [];
$target = 0; $usl = 0; $lsl = 0; $toleransi = 0;
$is_spec_valid = false;

if (!empty($search_code)) {
    $spc_code_edit = $search_code;
    
    $sqlGetHeader = "
        SELECT h.*, m.PartName, m.Target_Dimensi, m.USL, m.LSL 
        FROM SPC_Inspection_Header h
        LEFT JOIN Master_Part_Spec m ON h.PartNo = m.PartNo AND h.Dimensi = m.Dimensi
        WHERE h.SpcCode = ?
    ";
    $stmtGetHeader = sqlsrv_query($conn, $sqlGetHeader, array($spc_code_edit));
    
    if ($stmtGetHeader && $rowH = sqlsrv_fetch_array($stmtGetHeader, SQLSRV_FETCH_ASSOC)) {
        $edit_mode = true;
        $spc_id_edit = $rowH['SpcID']; 
        $header_edit['PartNo'] = $rowH['PartNo'];
        $header_edit['PartName'] = $rowH['PartName'];
        $header_edit['Customer'] = isset($rowH['Customer']) ? $rowH['Customer'] : '';
        $header_edit['Periode'] = isset($rowH['Periode']) ? $rowH['Periode'] : '';
        $header_edit['Dimensi'] = isset($rowH['Dimensi']) ? $rowH['Dimensi'] : '';
        $header_edit['Cavity'] = isset($rowH['Cavity']) ? $rowH['Cavity'] : '';
        $header_edit['Inspector'] = $rowH['Inspector'];
        
        if (!empty($rowH['Label_Tanggal'])) {
            $decoded = json_decode($rowH['Label_Tanggal'], true);
            if (is_array($decoded)) {
                $label_tgl_arr = $decoded;
            }
        }
        
        $target = (float)$rowH['Target_Dimensi'];
        $usl = (float)$rowH['USL'];
        $lsl = (float)$rowH['LSL'];
        $is_spec_valid = ($usl > 0 || $lsl > 0);
        $toleransi = $usl - $target; 
        
        $sqlGetDetail = "SELECT * FROM SPC_Inspection_Detail WHERE SpcID = ? ORDER BY Sampel_Ke ASC";
        $stmtGetDetail = sqlsrv_query($conn, $sqlGetDetail, array($spc_id_edit));
        if ($stmtGetDetail) {
            while ($rowD = sqlsrv_fetch_array($stmtGetDetail, SQLSRV_FETCH_ASSOC)) {
                $shift_ke = (int)$rowD['Sampel_Ke'];
                for ($d = 1; $d <= 31; $d++) {
                    if (isset($rowD["Val$d"]) && $rowD["Val$d"] !== null) {
                        $detail_edit[$shift_ke][$d] = (float)$rowD["Val$d"];
                    }
                }
            }
        }

        for ($d = 1; $d <= 31; $d++) {
            $lbl = !empty($label_tgl_arr[$d]) ? $label_tgl_arr[$d] : "Tgl $d";
            $chart_labels[] = $lbl;
            $chart_data_s1[] = ($detail_edit[1][$d] !== '') ? $detail_edit[1][$d] : null;
            $chart_data_s2[] = ($detail_edit[2][$d] !== '') ? $detail_edit[2][$d] : null;
            $chart_data_s3[] = ($detail_edit[3][$d] !== '') ? $detail_edit[3][$d] : null;
        }

    } else {
        $pesan = "<div class='alert alert-warning shadow-sm'>Data SPC <b>'$search_code'</b> tidak ditemukan.</div>";
    }
}

$tampil_spc_code = $edit_mode ? $spc_code_edit : $next_spc_code;
$chart_data_usl = array_fill(0, count($chart_labels), $usl);
$chart_data_lsl = array_fill(0, count($chart_labels), $lsl);
$chart_data_target = array_fill(0, count($chart_labels), $target);

// ==========================================
// 4. PROSES SIMPAN / UPDATE (POST SUBMIT)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btnSimpan'])) {
    sqlsrv_begin_transaction($conn);
    try {
        sqlsrv_query($conn, "SET ARITHABORT ON; SET ANSI_WARNINGS ON;");
        
        $spcId_Post = $_POST['spc_id']; 
        $partNo = $_POST['part_no'];
        $partName = $_POST['part_name']; 
        $customer = $_POST['customer'];
        $periode = $_POST['periode']; 
        $dimensi = $_POST['dimensi'];
        $cavity = $_POST['cavity'];
        $inspector = $_POST['inspector'];
        
        $label_tgl_post = isset($_POST['label_tgl']) ? $_POST['label_tgl'] : array_fill(1, 31, '');
        $label_tgl_json = json_encode($label_tgl_post);

        if(empty($partNo)){ throw new Exception("Part Number belum dipilih!"); }

        $sqlCheckMaster = "
            IF NOT EXISTS (SELECT 1 FROM Master_Part_Spec WHERE PartNo = ? AND Dimensi = ?) 
            BEGIN 
                INSERT INTO Master_Part_Spec (PartNo, PartName, Dimensi, Target_Dimensi, USL, LSL, Created_By) 
                VALUES (?, ?, ?, 0, 0, 0, ?) 
            END
        ";
        sqlsrv_query($conn, $sqlCheckMaster, array($partNo, $dimensi, $partNo, ($partName != '' ? $partName : 'Auto Inserted'), $dimensi, $inspector));

        if (!empty($spcId_Post)) {
            $spcId = $spcId_Post;
            $generated_code = $_POST['spc_code_lama']; 
            
            // PERBAIKAN: Shift diisi angka 0 (karena tinyint), Mesin diisi string '-'
            $sqlUpdateHeader = "UPDATE SPC_Inspection_Header SET PartNo=?, Periode=?, Dimensi=?, Cavity=?, Inspector=?, Customer=?, Label_Tanggal=?, Shift=0, Mesin='-' WHERE SpcID=?";
            $stmtUpd = sqlsrv_query($conn, $sqlUpdateHeader, array($partNo, $periode, $dimensi, $cavity, $inspector, $customer, $label_tgl_json, $spcId));
            
            if ($stmtUpd === false) {
                $err = sqlsrv_errors();
                throw new Exception("Gagal Update Header: " . $err[0]['message']);
            }
            
            sqlsrv_query($conn, "DELETE FROM SPC_Inspection_Detail WHERE SpcID=?", array($spcId));
        } else {
            $generated_code = $tampil_spc_code; 

            // PERBAIKAN: Nilai default Shift diisi 0 agar tipe data tinyint SQL Server tidak error
            $sqlInsertHeader = "INSERT INTO SPC_Inspection_Header (SpcCode, PartNo, Tgl_Inspeksi, Periode, Dimensi, Cavity, Inspector, Customer, Label_Tanggal, Shift, Mesin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, '-'); SELECT SCOPE_IDENTITY() AS SpcID;";
            $stmtHead = sqlsrv_query($conn, $sqlInsertHeader, array($generated_code, $partNo, date('Y-m-d'), $periode, $dimensi, $cavity, $inspector, $customer, $label_tgl_json));
            
            if ($stmtHead === false) {
                $err = sqlsrv_errors();
                throw new Exception("Gagal Simpan Header: " . $err[0]['message']);
            }
            
            sqlsrv_next_result($stmtHead);
            $rowHead = sqlsrv_fetch_array($stmtHead, SQLSRV_FETCH_ASSOC);
            $spcId = $rowHead['SpcID'];
        }

        $cols = "SpcID, Sampel_Ke";
        $placeholders = "?, ?";
        for($d=1; $d<=31; $d++) { $cols .= ", Val$d"; $placeholders .= ", ?"; }
        $sqlDetail = "INSERT INTO SPC_Inspection_Detail ($cols) VALUES ($placeholders)";
        
        for ($s = 1; $s <= 3; $s++) {
            $params = [$spcId, $s];
            for ($d = 1; $d <= 31; $d++) {
                $val = $_POST["val{$d}"][$s-1];
                $params[] = (trim($val) !== '') ? (float)$val : null;
            }
            $stmtDet = sqlsrv_query($conn, $sqlDetail, $params);
            if ($stmtDet === false) {
                $err = sqlsrv_errors();
                throw new Exception("Gagal Simpan Detail (Baris $s): " . $err[0]['message']);
            }
        }

        sqlsrv_commit($conn);
        echo "<script>window.location.href = 'dashboard_qc.php?page=spc&search_code=" . $generated_code . "';</script>";
        exit();

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $pesan = "<div class='alert alert-danger no-print'><strong><i class='bi bi-x-circle-fill'></i> SYSTEM ERROR:</strong> " . $e->getMessage() . "</div>";
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
        <h4 class="mb-0 fw-bold text-nowrap"><i class="bi bi-clipboard2-data-fill text-primary me-2"></i>Form SPC (Dinamis)</h4>
        
        <div class="d-flex flex-grow-1 mx-md-4 align-items-center gap-2" style="max-width: 650px;">
            <form action="dashboard_qc.php" method="GET" class="input-group input-group-sm shadow-sm" id="formSearchCode">
                <input type="hidden" name="page" value="spc">
                <span class="input-group-text bg-white text-primary"><i class="bi bi-search"></i></span>
                <select id="search_code" name="search_code" class="form-control" required>
                    <?php if($edit_mode && !empty($search_code)): ?>
                        <option value="<?php echo htmlspecialchars($search_code); ?>" selected="selected">
                            <?php echo htmlspecialchars($search_code); ?>
                        </option>
                    <?php endif; ?>
                </select>
                <button type="submit" class="btn btn-primary fw-bold d-none" id="btnCari">Cari</button>
                <?php if($edit_mode): ?>
                    <a href="dashboard_qc.php?page=spc" class="btn btn-danger btn-sm" title="Batal Edit"><i class="bi bi-x-circle"></i> Batal</a>
                <?php endif; ?>
            </form>
            
            <?php if($edit_mode): ?>
                <button onclick="window.print()" class="btn btn-sm btn-dark text-white fw-bold shadow-sm text-nowrap">
                    <i class="bi bi-printer-fill"></i> Print
                </button>
                <a href="dashboard_qc.php?page=spc&action=delete&code=<?php echo urlencode($search_code); ?>" class="btn btn-sm btn-danger fw-bold shadow-sm text-nowrap" onclick="return confirm('PERINGATAN: Yakin hapus permanen Dokumen SPC [<?php echo $search_code; ?>]?');">
                    <i class="bi bi-trash-fill"></i> Hapus
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="alert-container" class="no-print"><?php echo $pesan; ?></div>
    
    <form action="" method="POST" id="formSpc" class="no-print">
        <input type="hidden" name="spc_id" value="<?php echo htmlspecialchars($spc_id_edit); ?>">
        <input type="hidden" name="spc_code_lama" value="<?php echo htmlspecialchars($search_code); ?>">

        <div class="card shadow-sm mb-3 border-0 border-top <?php echo $edit_mode ? 'border-warning' : 'border-primary'; ?> border-3">
            <div class="card-header bg-white py-2">
                <h6 class="mb-0 fw-bold <?php echo $edit_mode ? 'text-warning' : 'text-primary'; ?>">1. Informasi Form (Header)</h6>
            </div>
            <div class="card-body py-3">
                <div class="row mb-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label fw-bold mb-1 small text-primary">Kode SPC</label>
                        <input type="text" class="form-control form-control-sm bg-light fw-bold text-primary" value="<?php echo htmlspecialchars($tampil_spc_code); ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-1 small text-danger">Part Number / Part Code</label>
                        <select id="select_part" name="part_no" class="form-select form-select-sm" required>
                            <?php if(!empty($header_edit['PartNo'])): ?>
                                <option value="<?php echo htmlspecialchars($header_edit['PartNo']); ?>" selected="selected">
                                    <?php echo htmlspecialchars($header_edit['PartNo'] . ' - ' . $header_edit['PartName']); ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1 small">Part Name</label>
                        <input type="text" id="part_name" name="part_name" class="form-control form-control-sm bg-light" readonly value="<?php echo htmlspecialchars($header_edit['PartName']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1 small">Customer</label>
                        <input type="text" id="customer" name="customer" class="form-control form-control-sm bg-light" readonly value="<?php echo htmlspecialchars($header_edit['Customer']); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label class="form-label fw-bold mb-1 small text-success">Periode Laporan</label>
                        <input type="text" name="periode" class="form-control form-control-sm border-success fw-bold" placeholder="Misal: Agt - Des 2026" value="<?php echo htmlspecialchars($header_edit['Periode']); ?>" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label fw-bold mb-1 small text-primary">Dimensi yang Diukur</label>
                        <input type="text" name="dimensi" class="form-control form-control-sm border-primary" value="<?php echo htmlspecialchars($header_edit['Dimensi']); ?>" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label fw-bold mb-1 small text-primary">Cavity</label>
                        <input type="text" name="cavity" class="form-control form-control-sm border-primary fw-bold" placeholder="Contoh: 09" value="<?php echo htmlspecialchars($header_edit['Cavity']); ?>" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label fw-bold mb-1 small">Nama Inspector</label>
                        <input type="text" name="inspector" class="form-control form-control-sm" value="<?php echo htmlspecialchars($header_edit['Inspector']); ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 border-top border-success border-3 mb-4">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-success">2. Data Pengukuran (Tgl Dinamis) x 31 Kolom</h6>
                <small class="text-muted"><i class="bi bi-info-circle"></i> Ubah header kolom dengan tanggal yang sesuai (misal: 01/08).</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 500px; overflow-x: auto;">
                    <table class="table table-bordered mb-0 table-grid" id="tableSpcDetail">
                        <thead class="bg-light">
                            <tr>
                                <th class="sticky-corner text-center text-dark align-middle" style="min-width: 100px;">SHIFT \ TGL</th>
                                <?php for($d=1; $d<=31; $d++): ?>
                                    <th style="min-width: 75px; padding: 2px;">
                                        <input type="text" name="label_tgl[<?php echo $d; ?>]" class="form-control form-control-sm text-center fw-bold bg-white" placeholder="Tgl <?php echo $d; ?>" value="<?php echo htmlspecialchars($label_tgl_arr[$d]); ?>" style="font-size: 11px; color: #d63384;">
                                    </th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($s=1; $s<=3; $s++): ?>
                            <tr>
                                <td class="sticky-col text-center align-middle fw-bold text-success bg-white">Shift <?php echo $s; ?></td>
                                <?php for($d=1; $d<=31; $d++): ?>
                                    <td>
                                        <input type="number" step="0.001" name="val<?php echo $d; ?>[]" class="form-control form-control-sm input-grid" value="<?php echo $detail_edit[$s][$d]; ?>">
                                    </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white py-3 text-end">
                <button type="submit" name="btnSimpan" class="btn <?php echo $edit_mode ? 'btn-warning' : 'btn-primary'; ?> fw-bold px-4 py-2 shadow-sm">
                    <i class="bi <?php echo $edit_mode ? 'bi-pencil-square' : 'bi-save'; ?> me-2"></i> 
                    <?php echo $edit_mode ? 'UPDATE DATA SPC' : 'SIMPAN SEMUA DATA SPC'; ?>
                </button>
            </div>
        </div>
    </form>

    <!-- ========================================================== -->
    <!-- 5. TAMPILAN PRINT REPORT SPC (MENYERUPAI EXCEL/PDF) -->
    <!-- ========================================================== -->
    <?php if($edit_mode): ?>
    <div id="sectionReport" class="mt-5 mb-5">
        
        <?php if(!$is_spec_valid): ?>
            <div class="alert alert-danger shadow-sm border-0 py-2 mb-4 no-print">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Perhatian:</strong> Nilai USL dan LSL untuk Dimensi ini belum diatur. Silakan atur melalui menu <a href="dashboard_qc.php?page=master_spec" class="alert-link fw-bold">Master Spec SPC</a> agar Grafik Report dapat dibuat dengan benar.
            </div>
        <?php else: ?>
        
        <div class="report-paper shadow">
            
            <div class="text-center mb-4">
                <h4 class="fw-bold report-title m-0">STATISTIC PROCESS CONTROL</h4>
            </div>

            <div class="row mb-3">
                <div class="col-7">
                    <table class="table-info-header w-100">
                        <tr><td width="130" class="fw-bold">PART NUMBER</td><td width="10">:</td><td class="fw-bold fs-6"><?php echo htmlspecialchars($header_edit['PartNo']); ?></td></tr>
                        <tr><td class="fw-bold">PART NAME</td><td>:</td><td class="fw-bold fs-6"><?php echo htmlspecialchars($header_edit['PartName']); ?></td></tr>
                        <tr><td class="fw-bold">CUSTOMER</td><td>:</td><td class="fw-bold fs-6 text-primary"><?php echo htmlspecialchars($header_edit['Customer']); ?></td></tr>
                        <tr><td class="fw-bold">DIMENSI</td><td>:</td><td class="fw-bold fs-6 text-primary"><?php echo htmlspecialchars($header_edit['Dimensi']); ?></td></tr>
                        <tr><td class="fw-bold">PERIODE</td><td>:</td><td class="fw-bold fs-6"><?php echo htmlspecialchars($header_edit['Periode']); ?></td></tr>
                        <tr><td class="fw-bold">CAVITY</td><td>:</td><td class="fw-bold fs-6 text-danger"><?php echo htmlspecialchars($header_edit['Cavity']); ?></td></tr>
                        <tr><td class="fw-bold">SPEC</td><td>:</td><td class="fw-bold fs-6"><?php echo number_format($target, 2); ?> &plusmn; <?php echo number_format($toleransi, 2); ?></td></tr>
                    </table>
                </div>
                <div class="col-5 d-flex justify-content-end align-items-start">
                    <div class="text-center me-3">
                        <div class="small fw-bold mb-1">Point Check No.</div>
                        <table class="table-spec-box mb-0">
                            <tr>
                                <th>Spec.</th><th>Max</th><th>Low</th>
                            </tr>
                            <tr>
                                <td><?php echo number_format($target, 2); ?></td>
                                <td>+ <?php echo number_format($toleransi, 2); ?></td>
                                <td>- <?php echo number_format($toleransi, 2); ?></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td class="fw-bold"><?php echo number_format($usl, 2); ?></td>
                                <td class="fw-bold"><?php echo number_format($lsl, 2); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <table class="table-signature">
                        <tr><th width="33%">APPROVED</th><th width="33%">CHECKED</th><th width="33%">PREPARED</th></tr>
                        <tr>
                            <td><br><br><br>Budiyono</td>
                            <td><br><br><br>Rita Komalasari</td>
                            <td><br><br><br>Widi Astutik</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="chart-wrapper mb-3 border">
                <canvas id="spcChart" style="height: 300px; width: 100%;"></canvas>
            </div>

            <table class="table-report-data">
                <tr>
                    <th class="bg-light-blue" style="min-width: 70px;">Month /<br>Date</th>
                    <th colspan="31" class="bg-light-blue text-center"><?php echo htmlspecialchars($header_edit['Periode']); ?></th>
                </tr>
                <tr>
                    <th class="bg-light-blue"></th>
                    <?php for($d=1; $d<=31; $d++): ?>
                        <th class="text-center"><?php echo htmlspecialchars($label_tgl_arr[$d]); ?></th>
                    <?php endfor; ?>
                </tr>
                <tr>
                    <td class="fw-bold bg-light">STANDARD</td>
                    <?php for($d=1; $d<=31; $d++): ?><td class="text-center"><?php echo number_format($target, 2); ?></td><?php endfor; ?>
                </tr>
                <tr>
                    <td class="fw-bold bg-light">MAX</td>
                    <?php for($d=1; $d<=31; $d++): ?><td class="text-center"><?php echo number_format($usl, 2); ?></td><?php endfor; ?>
                </tr>
                <tr>
                    <td class="fw-bold bg-light">MIN</td>
                    <?php for($d=1; $d<=31; $d++): ?><td class="text-center"><?php echo number_format($lsl, 2); ?></td><?php endfor; ?>
                </tr>
                
                <?php for($s=1; $s<=3; $s++): ?>
                <tr>
                    <td class="fw-bold bg-yellow">SHIFT <?php echo $s; ?></td>
                    <?php for($d=1; $d<=31; $d++): 
                        $val_cell = $detail_edit[$s][$d];
                        $bg_cell = ($val_cell !== '') ? 'bg-yellow' : '';
                    ?>
                        <td class="text-center <?php echo $bg_cell; ?>"><?php echo ($val_cell !== '') ? number_format($val_cell, 3) : ''; ?></td>
                    <?php endfor; ?>
                </tr>
                <?php endfor; ?>
            </table>
            
            <div class="mt-2 small fw-bold text-muted">FM.EG.10-00</div>

        </div> 
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- LIBRARY & STYLING -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .table-grid th { font-size: 11px; text-transform: uppercase; background-color: #f8f9fa; text-align: center; border-bottom: 2px solid #dee2e6; vertical-align: middle;}
    .table-grid td { padding: 4px; vertical-align: middle; }
    .input-grid { font-size: 12px; text-align: center; padding: 2px 4px; border: 1px solid #ced4da; }
    .input-grid:focus { border-color: #86b7fe; outline: 0; box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25); }
    .sticky-corner { position: sticky; top: 0; left: 0; z-index: 4; background-color: #e9ecef; border-right: 2px solid #dee2e6; }
    .sticky-col { position: sticky; left: 0; z-index: 2; background-color: #f8f9fa; border-right: 2px solid #dee2e6; box-shadow: 2px 0 5px -2px rgba(0,0,0,0.1);}
    
    .select2-container .select2-selection--single { font-size: 13px; height: 31px; }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { line-height: 29px; }

    /* PAPER REPORT STYLE */
    .report-paper { background: #fff; padding: 30px 40px; border-radius: 4px; border: 1px solid #ccc; max-width: 1300px; margin: 0 auto; color: #000; font-family: 'Times New Roman', Times, serif; }
    .report-title { font-family: 'Times New Roman', serif; letter-spacing: 1px; }
    .table-info-header td { padding: 2px 5px; vertical-align: top; }
    
    .table-spec-box { border-collapse: collapse; text-align: center; font-size: 12px; font-family: 'Arial', sans-serif;}
    .table-spec-box th, .table-spec-box td { border: 1px solid #000; padding: 2px 6px; }
    
    .table-signature { border-collapse: collapse; text-align: center; font-size: 10px; font-family: 'Arial', sans-serif; width: 280px;}
    .table-signature th, .table-signature td { border: 1px solid #000; padding: 2px; }
    
    .table-report-data { border-collapse: collapse; width: 100%; font-size: 10px; font-family: 'Arial', sans-serif; margin-bottom: 5px;}
    .table-report-data th, .table-report-data td { border: 1px solid #000; padding: 3px 2px; }
    .bg-light-blue { background-color: #d0e0e3 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-yellow { background-color: #fff2cc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    
    /* CSS KHUSUS PRINT */
    @media print {
        @page { size: A4 landscape; margin: 5mm; }
        body { background-color: #fff; margin: 0; padding: 0; }
        .no-print, .navbar-custom, #formSpc, .card { display: none !important; }
        #sectionReport { display: block !important; margin: 0 !important; padding: 0 !important; }
        .report-paper { border: none !important; box-shadow: none !important; padding: 0 !important; max-width: 100%; width: 100%; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>

<script>
$(document).ready(function() {
    $('#search_code').select2({ theme: 'bootstrap-5', placeholder: 'Cari Kode Dokumen...', minimumInputLength: 1, ajax: { url: 'ajax_search_spc_code.php', dataType: 'json', delay: 300, data: function (params) { return { term: params.term }; }, processResults: function (data) { return { results: data.results }; }, cache: true }});
    $('#search_code').on('select2:select', function (e) { $('#formSearchCode').submit(); });
    
    $('#select_part').select2({ theme: 'bootstrap-5', placeholder: 'Cari Part Number...', minimumInputLength: 2, ajax: { url: 'ajax_get_item.php', dataType: 'json', delay: 250, data: function (params) { return { q: params.term }; }, processResults: function (data) { return { results: data.results }; }, cache: true }});
    
    $('#select_part').on('select2:select', function (e) { 
        var data = e.params.data; 
        $('#part_name').val(data.part_name); 
        $('#customer').val(data.cust_comp); 
    });
    
    $('#formSpc').on('submit', function(e) { if (!$('#select_part').val()) { e.preventDefault(); alert("Peringatan: Anda belum memilih Part Number!"); $('#select_part').select2('open'); }});
});

<?php if($edit_mode && $is_spec_valid): ?>
    const ctx = document.getElementById('spcChart').getContext('2d');
    
    const lbls = <?php echo json_encode($chart_labels); ?>;
    const dTarget = <?php echo json_encode($chart_data_target); ?>;
    const dUsl = <?php echo json_encode($chart_data_usl); ?>;
    const dLsl = <?php echo json_encode($chart_data_lsl); ?>;
    const dS1 = <?php echo json_encode($chart_data_s1); ?>;
    const dS2 = <?php echo json_encode($chart_data_s2); ?>;
    const dS3 = <?php echo json_encode($chart_data_s3); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: lbls,
            datasets: [
                { label: 'STANDARD', data: dTarget, borderColor: '#4a86e8', borderWidth: 2, pointRadius: 0, fill: false, tension: 0 },
                { label: 'MAX (USL)', data: dUsl, borderColor: '#cc0000', borderWidth: 2, pointRadius: 0, fill: false, tension: 0 },
                { label: 'MIN (LSL)', data: dLsl, borderColor: '#6aa84f', borderWidth: 2, pointRadius: 0, fill: false, tension: 0 },
                { label: 'SHIFT 1', data: dS1, borderColor: '#8e7cc3', borderWidth: 2, pointRadius: 4, pointBackgroundColor: '#8e7cc3', fill: false, tension: 0, spanGaps: true },
                { label: 'SHIFT 2', data: dS2, borderColor: '#45818e', borderWidth: 2, pointRadius: 4, pointBackgroundColor: '#45818e', fill: false, tension: 0, spanGaps: true },
                { label: 'SHIFT 3', data: dS3, borderColor: '#e69138', borderWidth: 2, pointRadius: 4, pointBackgroundColor: '#e69138', fill: false, tension: 0, spanGaps: true }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false, 
            plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 9 } } } },
            scales: {
                y: {
                    suggestedMax: <?php echo $usl + ($toleransi * 0.5); ?>,
                    suggestedMin: <?php echo $lsl - ($toleransi * 0.5); ?>,
                    ticks: { font: { size: 10 } }
                },
                x: { ticks: { font: { size: 9 }, maxRotation: 0 } }
            }
        }
    });
<?php endif; ?>
</script>