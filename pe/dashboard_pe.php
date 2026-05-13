<?php
// FILE: msii/pe/dashboard_pe.php
require_once 'MiddleWare/Auth.php'; 
require_once 'MiddleWare/RoleCheck.php';
require_once '../config/database_p1.php';

// =========================================================================
// 1. AJAX HANDLER UNTUK SELECT2 (PENCARIAN PART & CUST SANGAT CEPAT)
// =========================================================================
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $type = $_GET['ajax_search'];
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    $res = [];
    
    if ($type == 'part' && !empty($term)) {
        // Hanya tarik 20 data teratas agar sangat ringan
        $stmt = sqlsrv_query($conn, "SELECT TOP 20 ITEM_CODE, ITEM_NAME FROM ITEMS WHERE ITEM_NAME LIKE ? OR ITEM_CODE LIKE ?", ["%$term%", "%$term%"]);
        if($stmt) while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $res[] = ['id' => $r['ITEM_NAME'], 'text' => $r['ITEM_CODE'] . ' - ' . $r['ITEM_NAME']];
        }
    } elseif ($type == 'cust' && !empty($term)) {
        $stmt = sqlsrv_query($conn, "SELECT TOP 20 CUST_COMP FROM CUST WHERE CUST_COMP LIKE ?", ["%$term%"]);
        if($stmt) while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $res[] = ['id' => $r['CUST_COMP'], 'text' => $r['CUST_COMP']];
        }
    }
    echo json_encode(['results' => $res]);
    exit;
}

// =========================================================================
// 2. TANGKAP FILTER DENGAN BATASAN DEFAULT (1 BULAN)
// =========================================================================
$start = !empty($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end   = !empty($_GET['end']) ? $_GET['end'] : date('Y-m-t'); 
$filter_part = isset($_GET['filter_part']) ? trim($_GET['filter_part']) : '';
$filter_cust = isset($_GET['filter_cust']) ? trim($_GET['filter_cust']) : '';

// Hapus Data
if (isset($_GET['delete'])) {
    $delCode = intval($_GET['delete']);
    // Hapus child data (Actual Weight) dulu, baru master Trial-nya
    sqlsrv_query($conn, "DELETE FROM Trial_PE_WPart_ACT WHERE Trial_CODE = ?", array($delCode));
    $stmtDel = sqlsrv_query($conn, "DELETE FROM TRIAL_PE WHERE TRIAL_CODE = ?", array($delCode));
    if ($stmtDel) {
        echo "<script>alert('Data Trial #{$delCode} berhasil dihapus!'); window.location='dashboard_pe.php';</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Trial PE</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <style>
        body { background-color: #f4f7f6; font-family: "Segoe UI", Roboto, Arial, sans-serif; overflow-x: hidden; }
        #sidebar { width: 250px; height: 100vh; background: #1f2a36; color: white; position: fixed; top: 0; left: 0; z-index: 1050; display: flex; flex-direction: column; box-shadow: 3px 0 10px rgba(0,0,0,0.2); }
        #sidebar .brand { padding: 22px 20px; font-size: 18px; font-weight: 700; background: #1a232d; text-align: center; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { color: #aab0b6; padding: 12px 20px; font-size: 14.5px; border-left: 4px solid transparent; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: #2c3e50; color: #fff !important; border-left-color: #3498db; }
        .nav-link i { margin-right: 10px; font-size: 1.1rem; }
        .menu-label { padding: 20px 20px 8px 20px; font-size: 11px; text-transform: uppercase; color: #5b6e80; font-weight: 800; letter-spacing: 1px; }
        .sidebar-footer { margin-top: auto; padding: 15px 20px; background: #161e27; border-top: 1px solid rgba(255,255,255,0.05); }

        #content { padding-left: 250px; transition: all 0.3s; }
        .top-header { background: white; padding: 15px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: white; margin-bottom: 25px; padding: 25px; }
        .table-wrapper { border-radius: 12px; overflow: hidden; }
        .table thead th { background-color: #1f2a36; color: #ffffff; font-size: 0.85rem; text-transform: uppercase; padding: 15px; border: none; }
        .table tbody td { padding: 15px; vertical-align: middle; border-bottom: 1px solid #f0f2f5; font-size: 0.95rem; }
        .badge { font-size: 0.8rem; }
    </style>
</head>
<body>

<div class="d-flex w-100">
    <?php include 'includes/sidebar.php'; ?>

    <div id="content" class="w-100 pb-5">
        <div class="top-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-speedometer2 text-primary me-2"></i> Dashboard Trial Report</h4>
            <div class="text-muted small"><i class="bi bi-calendar3"></i> <?= date('d M Y') ?></div>
        </div>

        <div class="container-fluid px-4">
            
            <form method="GET" class="mb-4">
                <div class="card shadow-sm border-0" style="background-color: #f8f9fa; border-radius: 12px;">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label text-muted small fw-bold mb-1">Tanggal Awal</label>
                                <input type="date" name="start" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-muted small fw-bold mb-1">Tanggal Akhir</label>
                                <input type="date" name="end" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold mb-1">Nama / Kode Part</label>
                                <select name="filter_part" class="form-select form-select-sm select2-part" data-placeholder="Ketik part...">
                                    <?php if(!empty($filter_part)): ?>
                                        <option value="<?= htmlspecialchars($filter_part) ?>" selected><?= htmlspecialchars($filter_part) ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold mb-1">Customer</label>
                                <select name="filter_cust" class="form-select form-select-sm select2-cust" data-placeholder="Ketik customer...">
                                    <?php if(!empty($filter_cust)): ?>
                                        <option value="<?= htmlspecialchars($filter_cust) ?>" selected><?= htmlspecialchars($filter_cust) ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-12 d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm" title="Cari Data"><i class="bi bi-search"></i> Cari Data</button>
                                <div class="vr mx-1"></div>
                                <button type="submit" formaction="export_excel_pe.php" formtarget="_blank" class="btn btn-success btn-sm fw-bold shadow-sm" title="Export Excel"><i class="bi bi-file-earmark-excel"></i> Excel</button>
                                <button type="submit" formaction="cetak_batch_pe.php" formtarget="_blank" class="btn btn-warning btn-sm fw-bold shadow-sm" title="Cetak Report (Format A4)"><i class="bi bi-file-pdf"></i> Report</button>
                                <button type="submit" formaction="cetak_history_pe.php" formtarget="_blank" class="btn btn-info btn-sm fw-bold text-white shadow-sm" title="Cetak History per Item (Format Landscape)"><i class="bi bi-clock-history"></i> History by Item</button>
                                <div class="vr mx-1"></div>
                                <a href="input_trial_pe.php" class="btn btn-dark btn-sm fw-bold shadow-sm" title="Buat Trial Baru"><i class="bi bi-plus-lg"></i> Baru</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card-custom table-wrapper">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tableTrial">
                        <thead class="text-center">
                            <tr>
                                <th width="8%">NO. TRIAL</th>
                                <th width="12%">TANGGAL</th>
                                <th width="25%">PART NAME</th>
                                <th width="20%">CUSTOMER</th>
                                <th width="10%">OPERATION</th>
                                <th width="10%">JUDGE</th>
                                <th width="15%">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT T.*, I.ITEM_NAME, C.CUST_COMP, J.JUDGE_TRIAL 
                                    FROM TRIAL_PE T
                                    LEFT JOIN ITEMS I ON T.PART_CODE = I.ITEM_CODE
                                    LEFT JOIN CUST C ON T.CUST_ID = C.CUST_ID
                                    LEFT JOIN JUDGE_TRIAL J ON T.JUDGE_ID = J.ID
                                    WHERE 1=1 AND T.DATE BETWEEN ? AND ? ";
                            
                            $params = array($start, $end);

                            if (!empty($filter_part)) {
                                $sql .= " AND I.ITEM_NAME LIKE ? ";
                                $params[] = "%" . $filter_part . "%"; 
                            }
                            if (!empty($filter_cust)) {
                                $sql .= " AND C.CUST_COMP LIKE ? ";
                                $params[] = "%" . $filter_cust . "%";
                            }
                            
                            $sql .= " ORDER BY T.TRIAL_CODE DESC";
                            $stmt = sqlsrv_query($conn, $sql, $params);
                            
                            if ($stmt === false) {
                                echo "<tr><td colspan='7' class='text-center text-danger'>Error Database</td></tr>";
                            } else {
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    $tgl = $row['DATE'] instanceof DateTime ? $row['DATE']->format('d M Y') : date('d M Y', strtotime($row['DATE']));
                                    
                                    $badge = 'bg-secondary';
                                    $judge_text = $row['JUDGE_TRIAL'] ? $row['JUDGE_TRIAL'] : 'BELUM JUDGE';
                                    if (stripos($judge_text, 'OK') !== false) $badge = 'bg-success';
                                    elseif (stripos($judge_text, 'NG') !== false) $badge = 'bg-danger';
                                    elseif (stripos($judge_text, 'RE') !== false) $badge = 'bg-warning text-dark';
                                    
                                    echo "<tr>
                                            <td class='text-center fw-bold text-primary'>#{$row['TRIAL_CODE']}</td>
                                            <td class='text-center'>{$tgl}</td>
                                            <td class='fw-semibold'>{$row['ITEM_NAME']}</td>
                                            <td><small>{$row['CUST_COMP']}</small></td>
                                            <td class='text-center'>".($row['OPERATION'] ? $row['OPERATION'] : '-')."</td>
                                            <td class='text-center'><span class='badge rounded-pill px-3 py-2 {$badge}'>{$judge_text}</span></td>
                                            <td class='text-center'>
                                                <a href='cetak_trial_pe.php?code={$row['TRIAL_CODE']}' target='_blank' class='btn btn-sm btn-outline-info' title='Cetak'><i class='bi bi-printer'></i> Cetak</a>
                                                <a href='input_trial_pe.php?mode=load&code={$row['TRIAL_CODE']}' class='btn btn-sm btn-outline-primary' title='Edit'><i class='bi bi-pencil-square'></i> Edit</a>
                                                <a href='?delete={$row['TRIAL_CODE']}' class='btn btn-sm btn-outline-danger' title='Hapus' onclick='return confirm(\"Hapus Trial Code {$row['TRIAL_CODE']}?\")'><i class='bi bi-trash'></i></a>
                                            </td>
                                          </tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Inisialisasi DataTables
    $('#tableTrial').DataTable({
        "pageLength": 10,
        "ordering": false,
        "lengthChange": false,
        "pagingType": "simple_numbers", 
        "language": {
            "search": "Cari Cepat di Tabel:",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Tidak ada data",
            "emptyTable": "<div class='text-center text-muted py-4'><i class='bi bi-folder-x fs-1 d-block mb-2 text-secondary'></i>Belum ada data Trial sesuai filter pencarian Anda.</div>",
            "paginate": { "next": "Selanjutnya", "previous": "Sebelumnya" }
        }
    });

    // Inisialisasi Select2 AJAX Part
    $('.select2-part').select2({
        theme: 'bootstrap-5', width: '100%', allowClear: true,
        ajax: {
            url: '?ajax_search=part',
            dataType: 'json', delay: 250,
            data: function (params) { return { term: params.term }; },
            processResults: function (data) { return { results: data.results }; }
        }
    });

    // Inisialisasi Select2 AJAX Customer
    $('.select2-cust').select2({
        theme: 'bootstrap-5', width: '100%', allowClear: true,
        ajax: {
            url: '?ajax_search=cust',
            dataType: 'json', delay: 250,
            data: function (params) { return { term: params.term }; },
            processResults: function (data) { return { results: data.results }; }
        }
    });
});
</script>
</body>
</html>