<?php
// FILE: msii/qc/edit_usulan.php

// ==========================================
// 1. KONEKSI MANDIRI (Anti-Error $conn)
// ==========================================
if (!isset($conn) || $conn === false) {
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    $active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';
    
    if ($active_plant == 'p2') {
        if(!isset($_SESSION['erp_user'])) $_SESSION['erp_user'] = $_SESSION['db_user'];
        if(!isset($_SESSION['erp_pass'])) $_SESSION['erp_pass'] = $_SESSION['db_pass'];
        $_SESSION['server_sql'] = "192.168.0.9"; 
        require_once __DIR__ . '/../config/database.php';
    } else {
        require_once __DIR__ . '/../config/database_p1.php';
    }
}

// ==========================================
// 2. CEK PARAMETER URL & AMBIL DATA
// ==========================================
if (!isset($_GET['kode_usul'])) {
    // Jika tidak ada kode, kembalikan ke list
    echo "<script>window.location.href='dashboard_qc.php?page=usulan_perubahan&plant=p2';</script>";
    exit();
}
$kode_usul_edit = $_GET['kode_usul'];

$sql = "SELECT U.*, I.ITEM_NO, I.ITEM_NAME FROM USULAN_PERUBAHAN U LEFT JOIN ITEMS I ON U.ITEM_ID = I.ITEM_ID WHERE U.KODE_USUL = ?";
$stmt = sqlsrv_query($conn, $sql, [$kode_usul_edit]);
if ($stmt === false || !($data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    echo "<div class='alert alert-danger'>Data usulan dengan kode '{$kode_usul_edit}' tidak ditemukan.</div>";
    exit();
}

// ==========================================
// 3. AMBIL DATA MASTER UNTUK DROPDOWN
// ==========================================
$custList = [];
$sqlCust = "SELECT CUST_ID, CUST_COMP FROM CUST WHERE CUST_INACTIVE = 0 ORDER BY CUST_COMP";
$resCust = sqlsrv_query($conn, $sqlCust);
if($resCust) { while ($r = sqlsrv_fetch_array($resCust, SQLSRV_FETCH_ASSOC)) { $custList[] = $r; } }

$requestList = [];
$sqlReq = "SELECT DISTINCT request FROM usulan_request ORDER BY request";
$resReq = sqlsrv_query($conn, $sqlReq);
if($resReq) { while ($r = sqlsrv_fetch_array($resReq, SQLSRV_FETCH_ASSOC)) { $requestList[] = $r['request']; } }

$remarkList = [];
$sqlRem = "SELECT DISTINCT keterangan FROM usulan_remark ORDER BY keterangan";
$resRem = sqlsrv_query($conn, $sqlRem);
if($resRem) { while ($r = sqlsrv_fetch_array($resRem, SQLSRV_FETCH_ASSOC)) { $remarkList[] = $r['keterangan']; } }
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    .form-label { font-size: 0.85rem; font-weight: 600; color: #495057; margin-bottom: 0.25rem; }
    .card-qc { border: none; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 1.25rem; }
    .card-header-qc { background-color: #fff; border-bottom: 2px solid #f0f2f5; padding: 12px 20px; font-weight: 700; color: #1f2a36; border-radius: 8px 8px 0 0 !important; }
    .card-header-qc i { color: #0d6efd; margin-right: 8px; }
    .custom-check-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 8px 12px; transition: 0.2s; }
    .custom-check-box:hover { background: #e9ecef; }
    .custom-check-box .form-check-label { font-size: 0.85rem; font-weight: 500; cursor: pointer; margin-left: 5px; }
    .select2-container--bootstrap-5 .select2-selection { font-size: 0.875rem; min-height: 31px; }
    .select2-container--bootstrap-5 .select2-selection--single { padding: 0.25rem 0.75rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-secondary m-0"><i class="bi bi-pencil-square text-warning"></i> EDIT USULAN (<?= htmlspecialchars($data['KODE_USUL']) ?>)</h4>
    <a href="dashboard_qc.php?page=usulan_perubahan&plant=p2" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Kembali ke List
    </a>
</div>

<form method="POST" action="update_usulan.php">
    <input type="hidden" name="kode_usul_edit" value="<?= htmlspecialchars($data['KODE_USUL']) ?>">

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card card-qc">
                <div class="card-header-qc"><i class="bi bi-info-square"></i> Informasi Dokumen</div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Kode Usulan</label>
                            <input type="text" name="kode_usul" class="form-control form-control-sm fw-bold bg-light" value="<?= htmlspecialchars($data['KODE_USUL']) ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Issue Date</label>
                            <input type="date" name="issue_date" class="form-control form-control-sm" value="<?= $data['ISSUE_DATE'] ? $data['ISSUE_DATE']->format('Y-m-d') : '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Request By</label>
                            <select name="pj" class="form-select form-select-sm select2-static">
                                <option value="">-- Pilih Dept/PIC --</option>
                                <?php foreach ($requestList as $r): ?>
                                    <option value="<?= htmlspecialchars($r) ?>" <?= ($r == $data['PJ']) ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Customer <span class="text-danger">*</span></label>
                            <select name="cust_id" class="form-select form-select-sm select2-static" required>
                                <option value="">-- Pilih Customer --</option>
                                <?php foreach ($custList as $c): ?>
                                    <option value="<?= $c['CUST_ID'] ?>" <?= ($c['CUST_ID'] == $data['CUST_ID']) ? 'selected' : '' ?>><?= htmlspecialchars($c['CUST_COMP']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Part No</label>
                            <select name="item_id" id="item_id_ajax" class="form-select form-select-sm">
                                <?php if (!empty($data['ITEM_ID'])): ?>
                                    <option value='<?= $data['ITEM_ID'] ?>' selected><?= htmlspecialchars($data['ITEM_NO']) ?></option>
                                <?php else: ?>
                                    <option value=''>-- Cari Part No --</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Part Name</label>
                            <input type="text" id="part_name" name="part_name" class="form-control form-control-sm bg-light" value="<?= htmlspecialchars($data['ITEM_NAME']) ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-qc">
                <div class="card-header-qc"><i class="bi bi-pencil-square"></i> Detail Perubahan</div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Document Name</label>
                            <input type="text" name="judul_dok" class="form-control form-control-sm" value="<?= htmlspecialchars($data['JUDUL_DOK']) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label d-block">Tipe Usulan</label>
                            <div class="d-flex align-items-center bg-light p-2 rounded border">
                                <div class="form-check me-4 mb-0">
                                    <input class="form-check-input" type="radio" name="tipe_usulan" id="tipe_baru" value="BARU" <?= ($data['BARU']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-primary" for="tipe_baru">BARU</label>
                                </div>
                                <div class="form-check me-3 mb-0">
                                    <input class="form-check-input" type="radio" name="tipe_usulan" id="tipe_revisi" value="REVISI" <?= ($data['REVISI']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-danger" for="tipe_revisi">REVISI</label>
                                </div>
                                <div class="ms-auto d-flex align-items-center">
                                    <label class="form-label mb-0 me-2 text-muted">No. Revisi:</label>
                                    <input type="number" name="revisi_1" class="form-control form-control-sm text-center" value="<?= htmlspecialchars($data['REVISI_1']) ?>" style="width: 80px;">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Isi Revisi / Perubahan</label>
                            <textarea name="isi_revisi" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($data['ISI_REVISI']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alasan Revisi (Reason)</label>
                            <textarea name="alasan_revisi" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($data['ALASAN_REVISI']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-qc">
                <div class="card-header-qc"><i class="bi bi-ui-checks"></i> Item Document Support</div>
                <div class="card-body p-3">
                    <div class="row gy-2 gx-2">
                        <div class="col-6"><div class="custom-check-box"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="is_std" value="1" id="is_std" <?= ($data['IS_STD']) ? 'checked' : '' ?>><label class="form-check-label" for="is_std">IS STD</label></div></div></div>
                        <div class="col-6"><div class="custom-check-box"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="fmea" value="1" id="fmea" <?= ($data['FMEA']) ? 'checked' : '' ?>><label class="form-check-label" for="fmea">FMEA</label></div></div></div>
                        <div class="col-6"><div class="custom-check-box"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="wi" value="1" id="wi" <?= ($data['WI']) ? 'checked' : '' ?>><label class="form-check-label" for="wi">Work Inst.</label></div></div></div>
                        <div class="col-6"><div class="custom-check-box"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="qcpc" value="1" id="qcpc" <?= ($data['QCPC']) ? 'checked' : '' ?>><label class="form-check-label" for="qcpc">QCPC</label></div></div></div>
                        <div class="col-6"><div class="custom-check-box"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="check_point" value="1" id="check_point" <?= ($data['CHECK_POINT']) ? 'checked' : '' ?>><label class="form-check-label" for="check_point">Check Point</label></div></div></div>
                        <div class="col-6"><div class="custom-check-box"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="setting_par" value="1" id="setting_par" <?= ($data['SETTING_PAR']) ? 'checked' : '' ?>><label class="form-check-label" for="setting_par">Setting Par</label></div></div></div>
                        <div class="col-6"><div class="custom-check-box"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="std_pack" value="1" id="std_pack" <?= ($data['STD_PACK']) ? 'checked' : '' ?>><label class="form-check-label" for="std_pack">STD Pack</label></div></div></div>
                        <div class="col-6"><div class="custom-check-box"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="other" value="1" id="other" <?= ($data['OTHER']) ? 'checked' : '' ?>><label class="form-check-label" for="other">Other</label></div></div></div>
                    </div>
                </div>
            </div>

            <div class="card card-qc">
                <div class="card-header-qc"><i class="bi bi-calendar-check"></i> Target & Remark</div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <label class="form-label">Plan Date</label>
                        <input type="date" name="plant_date" class="form-control form-control-sm" value="<?= $data['PLANT_DATE'] ? $data['PLANT_DATE']->format('Y-m-d') : '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Date</label>
                        <input type="date" name="target_date" class="form-control form-control-sm" value="<?= $data['TARGET_DATE'] ? $data['TARGET_DATE']->format('Y-m-d') : '' ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Remark / Status</label>
                        <select name="remarks" class="form-select form-select-sm select2-static">
                            <option value="">-- Pilih Status --</option>
                            <?php foreach ($remarkList as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>" <?= ($r == $data['REMARKS']) ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-warning py-2 fw-bold shadow-sm text-dark">
                    <i class="bi bi-save me-1"></i> SIMPAN PERUBAHAN
                </button>
                <a href="dashboard_qc.php?page=usulan_perubahan&plant=p2" class="btn btn-light border py-2 text-secondary fw-bold">
                    BATAL
                </a>
            </div>
        </div>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-static').select2({ theme: "bootstrap-5", width: '100%' });

// Inisialisasi Select2 AJAX (Untuk Part No)
    $('#item_id_ajax').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: '-- Pilih / Cari Part No --',
        ajax: {
            // URL DINAMIS: Jika di Plant 1 pakai search_items_p1.php
            url: '<?= ($active_plant == "p1") ? "search_items_p1.php" : "search_items.php" ?>', 
            dataType: 'json',
            delay: 250, 
            data: function (params) { return { term: params.term, page: params.page }; },
            processResults: function (data, params) {
                params.page = params.page || 1;
                return { results: data.results, pagination: { more: data.pagination.more } };
            },
            cache: true
        },
        templateResult: function (data) {
            if (data.loading) return data.text;
            return $("<div class='select2-result-repository__title'>" + data.text + "</div>");
        },
        templateSelection: function (data) {
            return data.item_no ? data.item_no : data.text; 
        }
    });

    $('#item_id_ajax').on('select2:select', function (e) { $('#part_name').val(e.params.data.item_name || ''); });
    $('#item_id_ajax').on('select2:unselect', function (e) { $('#part_name').val(''); });
});
</script>