<div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-card-list"></i> Daftar Report PICA</h5>
        <a href="?page=input_pica" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Tambah Data</a>
    </div>
    <div class="card-body bg-white p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>No TR</th>
                        <th>Tanggal</th>
                        <th>Customer</th>
                        <th>Problem</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Ambil data dari tabel PICA_HEADER, urutkan dari yang terbaru
                    $sql = "SELECT PicaID, NoTR, CONVERT(varchar, PicaDate, 23) as PicaDate, Customer, ProblemTitle 
                            FROM PICA_HEADER 
                            ORDER BY PicaID DESC";
                    $stmt = sqlsrv_query($conn, $sql);
                    
                    if ($stmt === false) {
                        echo "<tr><td colspan='6' class='text-center text-danger py-3'>Terjadi kesalahan saat mengambil data database.</td></tr>";
                    } else {
                        $hasData = false;
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            $hasData = true;
                            echo "<tr>";
                            echo "<td class='ps-3 fw-bold text-muted'>#".$row['PicaID']."</td>";
                            echo "<td>".$row['NoTR']."</td>";
                            echo "<td>".$row['PicaDate']."</td>";
                            echo "<td>".$row['Customer']."</td>";
                            echo "<td>".$row['ProblemTitle']."</td>";
                            echo "<td class='text-center'>
                                    <a href='?page=edit_pica&id=".$row['PicaID']."' class='btn btn-sm btn-warning text-dark' title='Edit'><i class='bi bi-pencil'></i> Edit</a>
                                    <a href='cetak_pica.php?id=".$row['PicaID']."' target='_blank' class='btn btn-sm btn-secondary text-white' title='Cetak Laporan'><i class='bi bi-printer'></i> Cetak</a>
                                  </td>";
                            echo "</tr>";
                        }
                        if (!$hasData) {
                            echo "<tr><td colspan='6' class='text-center py-4 text-muted'>Belum ada data PICA yang tersimpan.</td></tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>