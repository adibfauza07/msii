<?php
// FILE: exim/page_bc27.php
require_once __DIR__ . '/../config/database_p1.php';

$di_id = isset($_GET['di_id']) ? $_GET['di_id'] : '';
$items = [];
$header = null;

if (!empty($di_id)) {
    // Memanggil Stored Procedure SP_BC27
    $sql = "{call SP_BC27(?)}";
    $params = array(array($di_id, SQLSRV_PARAM_IN));
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo "<div class='alert alert-danger'>Error: " . print_r(sqlsrv_errors(), true) . "</div>";
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if (!$header) $header = $row;
            $items[] = $row;
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark"><i class="bi bi-file-earmark-arrow-up"></i> Draft BC 2.7 (TPB)</h3>
        <span class="badge bg-primary px-3 py-2">Source: SQL Server SP_BC27</span>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <input type="hidden" name="page" value="bc27">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-hash"></i></span>
                        <input type="number" name="di_id" class="form-control border-start-0" placeholder="Masukkan ID Pengiriman (DI_ID)..." value="<?= $di_id ?>" required>
                        <button class="btn btn-dark" type="submit">Cari Data</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($header): ?>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold"><i class="bi bi-building"></i> Informasi Entitas</div>
                <div class="card-body small">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted">Customer:</td><td class="fw-bold"><?= $header['CUST_COMP'] ?></td></tr>
                        <tr><td class="text-muted">NPWP:</td><td><?= $header['CUST_NPWP'] ?></td></tr>
                        <tr><td class="text-muted">Alamat:</td><td><?= $header['CUST_ADDR1'] ?>, <?= $header['CUST_CITY'] ?></td></tr>
                        <tr><td class="text-muted">Kantor:</td><td><?= $header['KPBC_CODE'] ?> - <?= $header['KPBC'] ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold"><i class="bi bi-file-text"></i> Dokumen Referensi</div>
                <div class="card-body small">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted">No. Invoice:</td><td class="fw-bold"><?= $header['DI_INVNO'] ?></td></tr>
                        <tr><td class="text-muted">Tgl Invoice:</td><td><?= $header['DI_DATE']->format('d-m-Y') ?></td></tr>
                        <tr><td class="text-muted">No. Packing:</td><td><?= $header['PACK_ID'] ?></td></tr>
                        <tr><td class="text-muted">Valuta:</td><td><?= $header['CURR_CODE'] ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-primary text-white">
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    <p class="mb-2">Data valid dan siap kirim?</p>
                    <button class="btn btn-light fw-bold w-100 mb-2" onclick="prosesCeisa(<?= $di_id ?>)">
                        <i class="bi bi-send-fill text-primary"></i> PUSH KE CEISA 4.0
                    </button>
                    <small class="opacity-75">Data akan diconvert ke JSON otomatis</small>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light text-uppercase">
                            <tr>
                                <th>No. Seri</th>
                                <th>Part Number</th>
                                <th>Deskripsi Barang</th>
                                <th class="text-end">Qty</th>
                                <th>Satuan</th>
                                <th class="text-end">Berat (KG)</th>
                                <th class="text-end">Total Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_nilai = 0;
                            foreach ($items as $item): 
                                $total_nilai += $item['NILAI'];
                            ?>
                            <tr>
                                <td><?= $item['DIPA_LINO'] ?></td>
                                <td class="fw-bold"><?= $item['PART_NO'] ?></td>
                                <td><?= $item['PART_NAME'] ?></td>
                                <td class="text-end"><?= number_format($item['QTY'], 2) ?></td>
                                <td><?= $item['PART_UNIT'] ?></td>
                                <td class="text-end"><?= number_format($item['PART_WEIGHT'] * $item['QTY'], 3) ?></td>
                                <td class="text-end fw-bold"><?= number_format($item['NILAI'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="6" class="text-end">GRAND TOTAL:</td>
                                <td class="text-end text-primary"><?= number_format($total_nilai, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($di_id): ?>
        <div class="alert alert-warning border-0 shadow-sm"><i class="bi bi-exclamation-circle"></i> Data DI ID tidak ditemukan.</div>
    <?php endif; ?>
</div>

<script>
function prosesCeisa(id) {
    if(confirm('Kirim dokumen ini ke Portal CEISA?')) {
        window.location.href = 'proses_kirim_ceisa.php?di_id=' + id;
    }
}
</script>