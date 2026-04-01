<?php
// Pastikan koneksi database sudah ada
if (!isset($conn)) { die("Direct access not allowed."); }

// Query yang aman: Hanya mengambil kolom yang pasti ada di tabel PROSES_CHANGE
$sql = "SELECT 
            P.CONTROL_ID, 
            P.CONTROL_NO, 
            P.CONTROL_DATE1, 
            P.MODEL, 
            P.PIC_NAME, 
            P.STATUS,
            I.ITEM_NAME -- Kita ambil nama item dari join
        FROM PROSES_CHANGE P
        LEFT JOIN ITEMS I ON P.ITEM_ID = I.ITEM_ID -- Join ke master items
        ORDER BY P.CONTROL_ID DESC";

$query = q($sql); // Memanggil fungsi q()
?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Riwayat Perubahan PCIS</h5>
        <div class="table-responsive">
            <table class="table table-hover table-sm" style="font-size: 12px;">
                <thead class="table-light">
                    <tr>
                        <th>Control No</th>
                        <th>Tanggal</th>
                        <th>Model</th>
                        <th>Part Name</th>
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
                        <td><?php echo $row['ITEM_NAME'] ; ?></td>
                        <td><?php echo $row['PIC_NAME']; ?></td>
                        <td>
                            <span class="badge bg-<?php echo ($row['STATUS'] == 'CLOSE') ? 'success' : 'warning text-dark'; ?>">
                                <?php echo $row['STATUS'] == 'CLOSE' ? 'CLOSE' : 'OPEN'; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="print_pcis.php?no=<?php echo $row['CONTROL_NO']; ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Print"><i class="bi bi-printer"></i></a>
                                <a href="?page=input_change&id=<?php echo $row['CONTROL_ID']; ?>" class="btn btn-outline-primary btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="delete_4m.php?id=<?php echo $row['CONTROL_ID']; ?>" onclick="return confirm('Yakin hapus data ini?')" class="btn btn-outline-danger btn-sm" title="Hapus"><i class="bi bi-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>