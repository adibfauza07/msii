<?php
// user_log.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/database.php';

$tglAwal  = isset($_GET['tglawal']) ? trim($_GET['tglawal']) : date('Y-m-01');
$tglAkhir = isset($_GET['tglakhir']) ? trim($_GET['tglakhir']) : date('Y-m-d');
$filterAksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$cari     = isset($_GET['cari']) ? trim($_GET['cari']) : '';

$where = " WHERE CAST(LOG_DATE AS DATE) >= ? AND CAST(LOG_DATE AS DATE) <= ? ";
$params = array($tglAwal, $tglAkhir);

if ($filterAksi !== '') {
    $where .= " AND [ACTION] = ? ";
    $params[] = $filterAksi;
}

if ($cari !== '') {
    $where .= " AND (USERNAME LIKE ? OR MODULE LIKE ? OR REFERENCE_NO LIKE ? OR DETAILS LIKE ?) ";
    $like = '%' . $cari . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT TOP 500 LOG_ID, LOG_DATE, USERNAME, [ACTION], MODULE, REFERENCE_NO, DETAILS, IP_ADDRESS 
        FROM dbo.APP_USER_LOG " . $where . " ORDER BY LOG_DATE DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
$logs = array();
if ($stmt !== false) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $logs[] = $r;
    }
    sqlsrv_free_stmt($stmt);
}
?>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-history"></i> Riwayat Log Aktivitas Pengguna (Audit Trail)</h3>
    </div>
    <div class="box-body">
        <form method="GET" action="index.php" class="form-inline" style="margin-bottom: 15px;">
            <input type="hidden" name="page" value="user_log">
            
            <div class="form-group" style="margin-right: 10px;">
                <label>Periode: </label>
                <input type="date" name="tglawal" class="form-control input-sm" value="<?php echo htmlspecialchars($tglAwal); ?>">
                <label> s/d </label>
                <input type="date" name="tglakhir" class="form-control input-sm" value="<?php echo htmlspecialchars($tglAkhir); ?>">
            </div>

            <div class="form-group" style="margin-right: 10px;">
                <label>Aksi: </label>
                <select name="aksi" class="form-control input-sm">
                    <option value="">-- Semua Aksi --</option>
                    <option value="INPUT" <?php if($filterAksi == 'INPUT') echo 'selected'; ?>>INPUT (Tambah)</option>
                    <option value="EDIT" <?php if($filterAksi == 'EDIT') echo 'selected'; ?>>EDIT (Ubah)</option>
                    <option value="HAPUS" <?php if($filterAksi == 'HAPUS') echo 'selected'; ?>>HAPUS (Delete)</option>
                </select>
            </div>

            <div class="form-group" style="margin-right: 10px;">
                <input type="text" name="cari" class="form-control input-sm" placeholder="Ketik User / Modul / No Dok..." value="<?php echo htmlspecialchars($cari); ?>" style="width: 200px;">
            </div>

            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
            <a href="index.php?page=user_log" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Reset</a>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover" style="font-size: 12px;">
                <thead>
                    <tr style="background-color: #f4f4f4;">
                        <th style="width: 50px;" class="text-center">No</th>
                        <th style="width: 140px;" class="text-center">Waktu Log</th>
                        <th style="width: 100px;">User</th>
                        <th style="width: 90px;" class="text-center">Aksi</th>
                        <th style="width: 140px;">Modul / Menu</th>
                        <th style="width: 150px;">Referensi / Dokumen</th>
                        <th>Keterangan / Perubahan</th>
                        <th style="width: 110px;" class="text-center">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted" style="padding: 20px;">
                                <em>Tidak ada data riwayat aktivitas pada filter yang dipilih.</em>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = 1;
                        foreach ($logs as $row): 
                            $badgeClass = 'label-default';
                            if ($row['ACTION'] == 'INPUT') $badgeClass = 'label-success';
                            elseif ($row['ACTION'] == 'EDIT') $badgeClass = 'label-warning';
                            elseif ($row['ACTION'] == 'HAPUS') $badgeClass = 'label-danger';

                            $waktu = $row['LOG_DATE'] instanceof DateTime ? $row['LOG_DATE']->format('d-m-Y H:i:s') : $row['LOG_DATE'];
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td class="text-center"><?php echo $waktu; ?></td>
                            <td><strong><i class="fa fa-user"></i> <?php echo htmlspecialchars($row['USERNAME']); ?></strong></td>
                            <td class="text-center"><span class="label <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($row['ACTION']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['MODULE']); ?></td>
                            <td><code><?php echo htmlspecialchars($row['REFERENCE_NO']); ?></code></td>
                            <td><?php echo nl2br(htmlspecialchars($row['DETAILS'])); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($row['IP_ADDRESS']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>