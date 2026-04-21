<?php
// FILE: exim/page_status.php
require_once __DIR__ . '/../config/database_p1.php';

// Pastikan tanggal default menggunakan format Y-m-d untuk input HTML
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$period = isset($_GET['period']) ? (int)$_GET['period'] : 1;

$data_status = [];

if (!empty($start_date)) {
    // 1. FORMAT TANGGAL KE STRING AGAR SQL SERVER PAHAM
    // SP RPT_BC_INOUT membutuhkan @STARTDATE DATETIME
    $formatted_date = date('Y-m-d 00:00:00', strtotime($start_date));
    
    $sql = "{call RPT_BC_INOUT(?, ?)}";
    $params = array(
        array($formatted_date, SQLSRV_PARAM_IN),
        array($period, SQLSRV_PARAM_IN)
    );
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        // Tampilkan error detail untuk debugging
        echo "<div class='alert alert-danger p-2 small'>Error SQL: " . print_r(sqlsrv_errors(), true) . "</div>";
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data_status[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark"><i class="bi bi-cloud-check"></i> Monitoring Status CEISA</h3>
        <div class="text-end">
            <span class="badge bg-success">Connected to SQL Server</span>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="status">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Pilih Bulan & Tahun</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Rentang (Bulan)</label>
                    <select name="period" class="form-select">
                        <option value="1" <?= $period == 1 ? 'selected' : '' ?>>1 Bulan</option>
                        <option value="3" <?= $period == 3 ? 'selected' : '' ?>>3 Bulan</option>
                        <option value="6" <?= $period == 6 ? 'selected' : '' ?>>6 Bulan</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark w-100"><i class="bi bi-filter"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold text-primary">Histori Dokumen Pabean</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light">
                    <tr>
                        <th>Tgl Dokumen</th>
                        <th>Jenis BC</th>
                        <th>Nomor BC</th>
                        <th>Arah</th>
                        <th>Entitas (Customer/Supplier)</th>
                        <th>Item Code</th>
                        <th class="text-end">Qty</th>
                        <th>Satuan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data_status)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">Tidak ada data ditemukan untuk periode ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data_status as $row): ?>
                        <tr>
                            <td><?= $row['BC_DATE']->format('d/m/Y') ?></td> <td><span class="badge bg-info text-dark"><?= $row['BCTY_NAME'] ?></span></td> <td class="fw-bold"><?= $row['BC_NO'] ?></td> <td>
                                <?php if ($row['BC_INOUT'] == 1): ?> <span class="text-success"><i class="bi bi-arrow-down-left-circle"></i> MASUK</span>
                                <?php else: ?>
                                    <span class="text-danger"><i class="bi bi-arrow-up-right-circle"></i> KELUAR</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $row['CUST_COMP'] ?: $row['SUP_COMP'] ?></td> <td><?= $row['ITEM_CODE'] ?></td> <td class="text-end"><?= number_format($row['IT_QTY'], 2) ?></td> <td><?= $row['ITEM_UNIT'] ?></td> <td>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Selesai</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>