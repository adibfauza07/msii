<?php

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);
// 1. Proteksi Halaman & Role (Pastikan path folder MiddleWare sudah benar)
require_once 'MiddleWare/Auth.php'; 

// 2. Koneksi Database
require_once '../config/database_p1.php';

// 3. Menangkap Parameter Filter dari Form
$start = !empty($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end   = !empty($_GET['end']) ? $_GET['end'] : date('Y-m-t'); 
$filter_part = isset($_GET['filter_part']) ? trim($_GET['filter_part']) : '';
$filter_cust = isset($_GET['filter_cust']) ? trim($_GET['filter_cust']) : '';

// 4. Logika Hapus Data (Delete)
if (isset($_GET['delete'])) {
    $del = intval($_GET['delete']);
    // Hapus child/detail data terlebih dahulu (Foreign Key)
    sqlsrv_query($conn, "DELETE FROM TRIAL_PE_DETAIL WHERE TRIAL_CODE = ?", array($del));
    sqlsrv_query($conn, "DELETE FROM Trial_PE_WPart_ACT WHERE Trial_CODE = ?", array($del));
    // Hapus master data
    $stmtDel = sqlsrv_query($conn, "DELETE FROM TRIAL_PE WHERE TRIAL_CODE = ?", array($del));

    if ($stmtDel) {
        echo "<script>alert('Data trial berhasil dihapus!'); window.location='dashboard_pe.php';</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PE Dashboard - Trial Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body { 
            background-color: #f4f7f6; 
            font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            overflow-x: hidden; 
        }
        
        #sidebar {
            width: 250px; height: 100vh; background: #1f2a36; color: white;
            position: fixed; top: 0; left: 0; z-index: 1050;
            display: flex; flex-direction: column;
            box-shadow: 3px 0 10px rgba(0,0,0,0.2);
        }

        #sidebar .brand {
            padding: 22px 20px; font-size: 18px; font-weight: 700;
            background: #1a232d; text-align: center; letter-spacing: 1px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .nav-link {
            color: #aab0b6; padding: 12px 20px; font-size: 14.5px;
            border-left: 4px solid transparent; transition: 0.3s;
        }

        .nav-link:hover, .nav-link.active {
            background: #2c3e50; color: #fff !important;
            border-left-color: #3498db;
        }

        .nav-link i { margin-right: 10px; font-size: 1.1rem; }

        .menu-label {
            padding: 20px 20px 8px 20px; font-size: 11px;
            text-transform: uppercase; color: #5b6e80; font-weight: 800;
            letter-spacing: 1px;
        }

        .sidebar-footer {
            margin-top: auto; padding: 15px 20px;
            background: #161e27; border-top: 1px solid rgba(255,255,255,0.05);
        }

        #content { 
            padding-left: 250px; 
            transition: all 0.3s;
        }

        .top-header {
            background: white; padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .card-custom {
            border: none; border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            background: white; margin-bottom: 25px;
        }

        .filter-label { font-size: 0.85rem; font-weight: 700; color: #6c757d; text-transform: uppercase; margin-bottom: 5px; }
        .form-control { border-radius: 8px; border: 1px solid #ced4da; padding: 10px 15px; }
        .form-control:focus { box-shadow: 0 0 0 0.25rem rgba(52,152,219,0.25); border-color: #3498db; }
        .btn-custom { border-radius: 8px; padding: 10px 20px; font-weight: 600; letter-spacing: 0.5px; }

        .table-wrapper { border-radius: 12px; overflow: hidden; }
        .table { margin-bottom: 0; }
        .table thead th {
            background-color: #1f2a36; color: #ffffff;
            font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;
            padding: 15px; border: none; font-weight: 600; vertical-align: middle;
        }
        .table tbody td {
            padding: 15px; vertical-align: middle;
            border-bottom: 1px solid #f0f2f5; font-size: 0.95rem; color: #495057;
        }
        .table tbody tr:hover { background-color: #f8f9fa; }
    </style>
</head>
<body>

<div class="d-flex w-100">

    <?php include 'includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        
        <div class="top-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0 fw-bold text-dark" style="letter-spacing: -0.5px;">
                <i class="bi bi-speedometer2 text-primary me-2"></i> Dashboard Trial Report
            </h4>
            <div class="text-muted small">
                <i class="bi bi-calendar3"></i> <?= date('d F Y') ?>
            </div>
        </div>

        <div class="container-fluid px-4">
            
            <div class="card-custom p-4">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="filter-label" style="font-size: 10px;">Tanggal Awal</label>
                        <input type="date" name="start" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label" style="font-size: 10px;">Tanggal Akhir</label>
                        <input type="date" name="end" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label" style="font-size: 10px;">Nama Part</label>
                        <input type="text" name="filter_part" class="form-control form-control-sm" placeholder="Ketik nama part..." value="<?= htmlspecialchars($filter_part) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label" style="font-size: 10px;">Customer</label>
                        <input type="text" name="filter_cust" class="form-control form-control-sm" placeholder="Ketik customer..." value="<?= htmlspecialchars($filter_cust) ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100 btn-sm fw-bold">
                            <i class="bi bi-search"></i> Cari
                        </button>
                    </div>
                    <div class="col-md-1 text-end">
                        <a href="input_trial_pe.php" class="btn btn-success w-100 btn-sm fw-bold shadow-sm" title="Buat Trial Baru">
                            <i class="bi bi-plus-lg"></i> Baru
                        </a>
                    </div>
                </form>
            </div>

            <div class="card-custom table-wrapper">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tableTrial">
                        <thead class="text-center">
                            <tr>
                                <th width="10%">NO. TRIAL</th>
                                <th width="12%">TANGGAL</th>
                                <th width="20%">PART NAME</th>
                                <th width="15%">CUSTOMER</th>
                                <th width="12%">OPERATION</th>
                                <th width="15%">JUDGE</th>
                                <th width="16%">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT T.*, I.ITEM_NAME, C.CUST_COMP, J.JUDGE_TRIAL 
                                    FROM TRIAL_PE T
                                    LEFT JOIN ITEMS I ON T.PART_CODE = I.ITEM_CODE
                                    LEFT JOIN CUST C ON T.CUST_ID = C.CUST_ID
                                    LEFT JOIN JUDGE_TRIAL J ON T.JUDGE_ID = J.ID
                                    WHERE 1=1 "; 
                            
                            $params = array();

                            if (!empty($start) && !empty($end)) {
                                $sql .= " AND T.DATE BETWEEN ? AND ? ";
                                $params[] = $start;
                                $params[] = $end;
                            }

                            if (!empty($filter_part)) {
                                $sql .= " AND I.ITEM_NAME LIKE ? ";
                                $params[] = "%" . trim($filter_part) . "%"; 
                            }

                            if (!empty($filter_cust)) {
                                $sql .= " AND C.CUST_COMP LIKE ? ";
                                $params[] = "%" . trim($filter_cust) . "%";
                            }

                            $sql .= " ORDER BY T.TRIAL_CODE DESC";
                            
                            $stmt = sqlsrv_query($conn, $sql, $params);
                            
                            if ($stmt === false) {
                                echo "<tr><td colspan='7' class='text-center text-danger py-4'>Error Database: " . print_r(sqlsrv_errors(), true) . "</td></tr>";
                            } else {
                                $hasData = false;
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    $hasData = true;
                                    
                                    $tgl = '-';
                                    if ($row['DATE'] instanceof DateTime) {
                                        $tgl = $row['DATE']->format('d M Y');
                                    } elseif (!empty($row['DATE'])) {
                                        $tgl = date('d M Y', strtotime($row['DATE']));
                                    }
                                    
                                    $badge = 'bg-secondary';
                                    $judge_text = $row['JUDGE_TRIAL'] ? $row['JUDGE_TRIAL'] : 'BELUM JUDGE';
                                    if (stripos($judge_text, 'OK') !== false) $badge = 'bg-success';
                                    elseif (stripos($judge_text, 'NG') !== false) $badge = 'bg-danger';
                                    elseif (stripos($judge_text, 'RE') !== false) $badge = 'bg-warning text-dark';
                                    
                                    echo "<tr>
                                            <td class='text-center fw-bold text-primary'>#{$row['TRIAL_CODE']}</td>
                                            <td class='text-center'>{$tgl}</td>
                                            <td class='fw-semibold'>{$row['ITEM_NAME']}</td>
                                            <td>{$row['CUST_COMP']}</td>
                                            <td class='text-center'>" . (isset($row['OPERATION']) ? $row['OPERATION'] : '-') . "</td>
                                            <td class='text-center'><span class='badge rounded-pill px-3 py-2 {$badge}'>{$judge_text}</span></td>
                                            <td class='text-center'>
                                                <a href='cetak_trial_pe.php?code={$row['TRIAL_CODE']}' target='_blank' class='btn btn-sm btn-outline-info me-1' title='Cetak Report'><i class='bi bi-printer'></i> Cetak</a>
<a href='input_trial_pe.php?mode=load&code={$row['TRIAL_CODE']}' class='btn btn-sm btn-outline-primary me-1' title='Edit'><i class='bi bi-pencil-square'></i> Edit</a>
<a href='?delete={$row['TRIAL_CODE']}' class='btn btn-sm btn-outline-danger' title='Hapus' onclick='return confirm(\"Yakin ingin menghapus data Trial Code {$row['TRIAL_CODE']}?\")'><i class='bi bi-trash'></i></a>
                                            </td>
                                          </tr>";
                                }
                                
                                if (!$hasData) {
                                     echo "<tr><td colspan='7' class='text-center text-muted py-4'><i class='bi bi-folder-x fs-1 d-block mb-2 text-secondary'></i>Belum ada data Trial sesuai filter pencarian Anda.</td></tr>";
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

<script>
$(document).ready(function() {
    $('#tableTrial').DataTable({
        "pageLength": 10,
        "ordering": false,
        "lengthChange": false,
        "language": {
            "search": "Cari Cepat:",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Tidak ada data",
            "emptyTable": "<div class='text-center text-muted py-4'><i class='bi bi-folder-x fs-1 d-block mb-2 text-secondary'></i>Belum ada data Trial pada rentang tanggal tersebut.</div>",
            "paginate": {
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            }
        }
    });
});
</script>
</body>
</html>