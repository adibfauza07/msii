<div class="modal fade" id="modalInputSales" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="process_sales.php" method="POST">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">INPUT INVOICE BARU</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="act" value="insert">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="small fw-bold">Customer</label>
                            <select name="cust_id" class="form-select select2-modal" style="width:100%" required>
                                <option value="">-- Ketik Nama Customer --</option>
                                <?php
                                $get_cust = q("SELECT CUST_ID, CUST_CODE, CUST_COMP FROM CUST ORDER BY CUST_COMP ASC");
                                while($c = sqlsrv_fetch_array($get_cust, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$c['CUST_ID']."'>".$c['CUST_CODE']." - ".$c['CUST_COMP']."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="small fw-bold">No. Invoice</label>
                            <input type="text" name="invoice_number" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">Faktur Pajak</label>
                            <input type="text" name="faktur_pajak" class="form-control">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="small fw-bold">Tgl Invoice</label>
                            <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">Jatuh Tempo</label>
                            <input type="date" name="due_date" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="small fw-bold">Mata Uang</label>
                            <select name="curr_code" class="form-select">
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="small fw-bold">Amount</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="small fw-bold">Kategori AR</label>
                            <select name="id_kategori_sales" class="form-select select2-modal" style="width:100%" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php
                                $get_kat2 = q("SELECT id_kategori_sales, SalesCode, SalesName FROM MasterKategoriSales ORDER BY SalesCode ASC");
                                while($k2 = sqlsrv_fetch_array($get_kat2, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$k2['id_kategori_sales']."'>".$k2['SalesCode']." - ".$k2['SalesName']."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">Account Receiveable (AR)</label>
                            <select name="id_biaya" class="form-select select2-modal" style="width:100%">
                                <option value="">-- Pilih Account --</option>
                                <?php
                                $get_biaya = q("SELECT id_biaya, AccountCode, AccountName FROM MasterBiayaSales ORDER BY AccountName ASC");
                                while($b = sqlsrv_fetch_array($get_biaya, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$b['id_biaya']."'>".$b['AccountCode']." - ".$b['AccountName']."</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary fw-bold">SIMPAN DATA</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditSales" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="process_sales.php" method="POST">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold">EDIT DATA SALES</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="act" value="update">
                    <input type="hidden" name="id_sales" id="edit_id_sales">

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="small fw-bold">Customer</label>
                            <select name="cust_id" id="edit_cust_id" class="form-select select2-edit" style="width:100%" required>
                                <option value="">-- Pilih Customer --</option>
                                <?php
                                // Kita harus query ulang untuk modal edit (atau simpan query sebelumnya di variable)
                                // Agar simple, kita query lagi (resource SQL Server ringan kok)
                                $get_cust2 = q("SELECT CUST_ID, CUST_CODE, CUST_COMP FROM CUST ORDER BY CUST_COMP ASC");
                                while($c2 = sqlsrv_fetch_array($get_cust2, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$c2['CUST_ID']."'>".$c2['CUST_CODE']." - ".$c2['CUST_COMP']."</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="small fw-bold">No. Invoice</label>
                            <input type="text" name="invoice_number" id="edit_invoice_number" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">Faktur Pajak</label>
                            <input type="text" name="faktur_pajak" id="edit_faktur_pajak" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="small fw-bold">Tgl Invoice</label>
                            <input type="date" name="invoice_date" id="edit_invoice_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">Jatuh Tempo</label>
                            <input type="date" name="due_date" id="edit_due_date" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="small fw-bold">Mata Uang</label>
                            <select name="curr_code" id="edit_curr_code" class="form-select">
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="small fw-bold">Amount</label>
                            <input type="number" step="0.01" name="amount" id="edit_amount" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="small fw-bold">Kategori AR</label>
                            <select name="id_kategori_sales" id="edit_id_kat_sales" class="form-select select2-edit" style="width:100%" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php
                                $get_kat2 = q("SELECT id_kategori_sales, SalesCode, SalesName FROM MasterKategoriSales ORDER BY SalesCode ASC");
                                while($k2 = sqlsrv_fetch_array($get_kat2, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$k2['id_kategori_sales']."'>".$k2['SalesCode']." - ".$k2['SalesName']."</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="small fw-bold">Account Receiveable (AR)</label>
                            <select name="id_biaya" id="edit_id_biaya" class="form-select select2-edit" style="width:100%">
                                <option value="">-- Pilih Account --</option>
                                <?php
                                $get_biaya = q("SELECT id_biaya, AccountCode, AccountName FROM MasterBiayaSales ORDER BY AccountName ASC");
                                while($b = sqlsrv_fetch_array($get_biaya, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$b['id_biaya']."'>".$b['AccountCode']." - ".$b['AccountName']."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_paid" id="edit_is_paid" value="1">
                                <label class="form-check-label fw-bold text-success" for="edit_is_paid">Tandai sebagai Lunas (Paid)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning fw-bold text-dark">UPDATE DATA</button>
                </div>
            </div>
        </form>
    </div>
</div>