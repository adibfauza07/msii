<?php
// Pastikan file ini dipanggil melalui dashboard
if (!isset($conn)) { die("Direct access not allowed."); }

// Query ambil data riwayat perubahan
$sql = "SELECT CONTROL_NO, CONTROL_DATE1, MODEL, PIC_NAME, STATUS 
        FROM PROSES_CHANGE 
        ORDER BY CONTROL_ID DESC";
$query = q($sql);
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2"></i>Riwayat Perubahan (PCIS)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Control No</th>
                        <th>Tanggal</th>
                        <th>Model</th>
                        <th>PIC</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td class="fw-bold text-primary"><?php echo $row['CONTROL_NO']; ?></td>
                        <td><?php echo $row['CONTROL_DATE1'] ? $row['CONTROL_DATE1']->format('d-m-Y') : '-'; ?></td>
                        <td><?php echo $row['MODEL']; ?></td>
                        <td><?php echo $row['PIC_NAME']; ?></td>
                        <td>
                            <span class="badge <?php echo ($row['STATUS'] == 'CLOSE') ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                <?php echo $row['STATUS'] ? $row['STATUS'] : '-'; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-info text-white"><i class="bi bi-eye"></i></button>
                            <button class="btn btn-sm btn-danger"><i class="bi bi-printer"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>