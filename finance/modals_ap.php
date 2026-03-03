<div class="modal fade" id="modalInputAP" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="process_ap.php" method="POST">
        <input type="hidden" name="act" value="insert">    
        <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">INPUT INVOICE AP (HUTANG)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="act" value="insert">
                    <div class="row g-3">
                        
                        <div class="col-md-12">
                            <label class="small fw-bold">Supplier</label>
                            <select name="SUP_ID" class="form-select select2-modal" style="width:100%" required>
                                <option value="">-- Ketik Nama Supplier --</option>
                                <?php
                                $get_sup = q("SELECT SUP_ID, SUP_COMP, SUP_CODE FROM SUPPLIER ORDER BY SUP_COMP ASC");
                                while($s = sqlsrv_fetch_array($get_sup, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$s['SUP_ID']."'>".$s['SUP_CODE']." - ".$s['SUP_COMP']."</option>";
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
                            <label class="small fw-bold">Kategori AP</label>
                            <select name="id_supplier_cat" class="form-select select2-modal" style="width:100%" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php
                                $get_kat = q("SELECT id_supplier, SupplierCode, SupplierName FROM MasterKategoriAP ORDER BY SupplierCode ASC");
                                while($k = sqlsrv_fetch_array($get_kat, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$k['id_supplier']."'>".$k['SupplierCode']." - ".$k['SupplierName']."</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="small fw-bold">Account Payable (AP)</label>
                            <select name="id_biaya" class="form-select select2-modal" style="width:100%">
                                <option value="">-- Pilih Account --</option>
                                <?php
                                $get_biaya2 = q("SELECT id_biaya, AccountCode, AccountName FROM MasterBiayaAP ORDER BY AccountCode ASC");
                                while($b2 = sqlsrv_fetch_array($get_biaya2, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$b2['id_biaya']."'>".$b2['AccountCode']." - ".$b2['AccountName']."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-12">
    <label class="small fw-bold">Deskripsi / Keterangan</label>
    <textarea name="deskripsi" class="form-control shadow-sm" rows="2" placeholder="Tulis keterangan invoice di sini..."></textarea>
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

<div class="modal fade" id="modalEditAP" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="process_ap.php" method="POST">
            <input type="hidden" name="act" value="update">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold">EDIT DATA AP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="act" value="update">
                    <input type="hidden" name="id_ap" id="edit_id_ap">

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="small fw-bold">Supplier</label>
                            <select name="SUP_ID" id="edit_sup_id" class="form-select select2-edit" style="width:100%" required>
                                <option value="">-- Pilih Supplier --</option>
                                <?php
                                $get_sup2 = q("SELECT SUP_ID, SUP_COMP, SUP_CODE FROM SUPPLIER ORDER BY SUP_COMP ASC");
                                while($s2 = sqlsrv_fetch_array($get_sup2, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$s2['SUP_ID']."'>".$s2['SUP_CODE']." - ".$s2['SUP_COMP']."</option>";
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
                            <label class="small fw-bold">Kategori AP</label>
                            <select name="id_supplier_cat" id="edit_id_kat_ap" class="form-select select2-edit" style="width:100%" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php
                               $get_kat = q("SELECT id_supplier, SupplierCode, SupplierName FROM MasterKategoriAP ORDER BY SupplierCode ASC");
        while($k = sqlsrv_fetch_array($get_kat, SQLSRV_FETCH_ASSOC)) {
            echo "<option value='".$k['id_supplier']."'>[".$k['SupplierCode']."] ".$k['SupplierName']."</option>";
        }
        ?>
                                ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="small fw-bold">Account Payable (AP)</label>
                            <select name="id_biaya" id="edit_id_biaya" class="form-select select2-edit" style="width:100%">
                                <option value="">-- Pilih Account --</option>
                                <?php
                                $get_biaya2 = q("SELECT id_biaya, AccountCode, AccountName FROM MasterBiayaAP ORDER BY AccountCode ASC");
                                while($b2 = sqlsrv_fetch_array($get_biaya2, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$b2['id_biaya']."'>".$b2['AccountCode']." - ".$b2['AccountName']."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-12">
    <label class="small fw-bold">Deskripsi / Keterangan</label>
    <textarea name="deskripsi" id="edit_deskripsi" class="form-control shadow-sm" rows="2"></textarea>
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
                    <button type="submit" name="update_ap" class="btn btn-warning fw-bold text-dark">SIMPAN PERUBAHAN</button>
                </div>
            </div>
        </form>
    </div>
</div>