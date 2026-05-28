<?php
require_once 'pcis_functions.php';

$id_edit = isset($_GET['id']) ? intval($_GET['id']) : 0;
$dataEdit = null;

if ($id_edit > 0) {
    // Ambil data lama untuk diedit
    $qData = q("SELECT * FROM PROSES_CHANGE WHERE CONTROL_ID = ?", array($id_edit));
    $dataEdit = sqlsrv_fetch_array($qData, SQLSRV_FETCH_ASSOC);
}

// Load Data Awal Dropdown
$qDept = q("SELECT DEP_CODE, DEP_NAME FROM DEPT WHERE DEP_CODE IN ('MS','PE','PC','MK','PD','QC','MA','PU') ORDER BY DEP_NAME");
$qCust = q("SELECT CUST_ID, CUST_COMP FROM CUST ORDER BY CUST_COMP");
$qStatus = q("SELECT STATUS FROM PROSES_STATUS");
?>

<div class="container-fluid pb-5">
    <form action="" method="POST" id="form4M">
        <input type="hidden" name="control_id" value="<?php echo $id_edit; ?>">

        <div class="row g-4">
            
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm pt-2" style="border-top: 4px solid #8b5cf6 !important;">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">CONTROL NO</label>
                                <input type="text" name="control_no" class="form-control form-control-sm bg-light fw-bold" 
                                       value="<?php echo ($dataEdit && isset($dataEdit['CONTROL_NO'])) ? rtrim($dataEdit['CONTROL_NO']) : getNewControlNumber(); ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">CONTROL DATE</label>
                                <input type="date" name="control_date" class="form-control form-control-sm" 
                                       value="<?php echo ($dataEdit && isset($dataEdit['CONTROL_DATE1']) && $dataEdit['CONTROL_DATE1'] instanceof DateTime) ? $dataEdit['CONTROL_DATE1']->format('Y-m-d') : date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">TO</label>
                                <input type="text" name="to_pcis" class="form-control form-control-sm" 
                                       value="<?php echo ($dataEdit && isset($dataEdit['TO_PCIS'])) ? rtrim($dataEdit['TO_PCIS']) : 'ALL DEPARTEMENT'; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">CC</label>
                                <input type="text" name="cc_pcis" class="form-control form-control-sm" 
                                       value="<?php echo ($dataEdit && isset($dataEdit['CC'])) ? rtrim($dataEdit['CC']) : 'ALL HEAD DEPT'; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold small text-uppercase text-primary border-0 pt-3">
                        <i class="bi bi-tools me-2"></i> Technical Change Details
                    </div>
                    <div class="card-body">
                        <div class="row g-3 p-3 mb-3 rounded border bg-light">
                            <div class="col-md-4">
                                <label class="small fw-bold">Request By</label>
                                <div class="mt-1">
                                    <div class="form-check small"><input class="form-check-input" type="checkbox" name="internal" <?php echo ($dataEdit && empty($dataEdit['INTERNAL'])) ? '' : 'checked'; ?>> Internal</div>
                                    <div class="form-check small"><input class="form-check-input" type="checkbox" name="customer" <?php echo ($dataEdit && !empty($dataEdit['CUSTOMER'])) ? 'checked' : ''; ?>> Customer</div>
                                    <div class="form-check small"><input class="form-check-input" type="checkbox" name="supplier" <?php echo ($dataEdit && !empty($dataEdit['SUPPLIER'])) ? 'checked' : ''; ?>> Supplier</div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label class="small fw-bold">Person in Charge (PIC)</label>
                                <input type="text" name="pic_name" class="form-control form-control-sm mb-2" placeholder="Nama PIC" value="<?php echo ($dataEdit && isset($dataEdit['PIC_NAME'])) ? rtrim($dataEdit['PIC_NAME']) : ''; ?>">
                                <select name="dep_code" class="form-select form-select-sm">
                                    <option value="">-- Pilih Departemen --</option>
                                    <?php while($d = sqlsrv_fetch_array($qDept, SQLSRV_FETCH_ASSOC)): ?>
                                        <option value="<?php echo $d['DEP_CODE']; ?>" <?php echo ($dataEdit && isset($dataEdit['DEP_CODE']) && rtrim($dataEdit['DEP_CODE']) == rtrim($d['DEP_CODE'])) ? 'selected' : ''; ?>><?php echo $d['DEP_NAME']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="small fw-bold">Customer Name</label>
                                <select id="select_cust" class="form-select form-select-sm">
                                    <option value="">-- Pilih Customer --</option>
                                    <?php while($c = sqlsrv_fetch_array($qCust, SQLSRV_FETCH_ASSOC)): ?>
                                        <option value="<?php echo $c['CUST_ID']; ?>" <?php echo ($dataEdit && isset($dataEdit['ITEM_ID']) && rtrim($dataEdit['ITEM_ID']) == rtrim($c['CUST_ID'])) ? 'selected' : ''; ?>><?php echo $c['CUST_COMP']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Part / Item Name</label>
                                <select name="item_id" id="select_item" class="form-select form-select-sm">
                                    <?php if ($dataEdit && isset($dataEdit['ITEM_ID'])): 
                                        $qCurrItem = q("SELECT ITEM_ID, PART_NO, PART_NAME FROM PC_ITEM_CUSTOMER_VIEW WHERE ITEM_ID = ?", array($dataEdit['ITEM_ID']));
                                        $currItem = sqlsrv_fetch_array($qCurrItem, SQLSRV_FETCH_ASSOC);
                                        if ($currItem): ?>
                                        <option value="<?php echo $currItem['ITEM_ID']; ?>" selected><?php echo $currItem['PART_NO']." - ".$currItem['PART_NAME']; ?></option>
                                    <?php endif; else: ?>
                                        <option value="">-- Pilih Customer Dulu --</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Model</label>
                                <input type="text" name="model" class="form-control form-control-sm" value="<?php echo ($dataEdit && isset($dataEdit['MODEL'])) ? rtrim($dataEdit['MODEL']) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Material (Teks Deskripsi)</label>
                                <input type="text" name="material_id" class="form-control form-control-sm" value="<?php echo ($dataEdit && isset($dataEdit['MATERIAL_ID'])) ? rtrim($dataEdit['MATERIAL_ID']) : ''; ?>">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small fw-bold border-bottom d-block mb-2">Item Change (4M Kategori)</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check small"><input class="form-check-input" type="checkbox" name="man" <?php echo ($dataEdit && !empty($dataEdit['MAN'])) ? 'checked' : ''; ?>> Man</div>
                                    <div class="form-check small"><input class="form-check-input" type="checkbox" name="machine" <?php echo ($dataEdit && !empty($dataEdit['MACHINE'])) ? 'checked' : ''; ?>> Machine</div>
                                    <div class="form-check small"><input class="form-check-input" type="checkbox" name="method" <?php echo ($dataEdit && !empty($dataEdit['METHOD'])) ? 'checked' : ''; ?>> Method</div>
                                    <div class="form-check small"><input class="form-check-input" type="checkbox" name="material_4m" <?php echo ($dataEdit && !empty($dataEdit['MATERIAL'])) ? 'checked' : ''; ?>> Material</div>
                                </div>
                            </div>
                            <div class="col-md-6 border-start ps-4">
                                <label class="small fw-bold border-bottom d-block mb-2">Changing Type</label>
                                <div class="form-check small"><input class="form-check-input" type="radio" name="perm" value="1" <?php echo ($dataEdit && empty($dataEdit['PERMANENT_CHANGE'])) ? '' : 'checked'; ?>> Permanent Change</div>
                                <div class="form-check small"><input class="form-check-input" type="radio" name="perm" value="0" <?php echo ($dataEdit && !empty($dataEdit['PERMANENT_CHANGE']) && $dataEdit['PERMANENT_CHANGE'] == 0) ? 'checked' : ''; ?>> Temporary Change</div>
                            </div>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-md-12">
                                <label class="small fw-bold">Reason / Purpose</label>
                                <textarea name="reason" class="form-control form-control-sm" rows="2"><?php echo ($dataEdit && isset($dataEdit['REASON'])) ? trim($dataEdit['REASON']) : ''; ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-danger">BEFORE CHANGE</label>
                                <textarea name="bef_change" class="form-control form-control-sm border-danger-subtle" rows="3"><?php echo ($dataEdit && isset($dataEdit['BEF_CHANGE'])) ? trim($dataEdit['BEF_CHANGE']) : ''; ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-success">AFTER CHANGE</label>
                                <textarea name="aft_change" class="form-control form-control-sm border-success-subtle" rows="3"><?php echo ($dataEdit && isset($dataEdit['AFT_CHANGE'])) ? trim($dataEdit['AFT_CHANGE']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold small text-uppercase text-primary border-0 pt-3">
                        <i class="bi bi-chat-square-text me-2"></i> Departmental Review
                    </div>
                    <div class="card-body">
                        <div class="row g-3 overflow-auto" style="max-height: 480px;">
                            <?php
                            // Mapping disesuaikan presisi dengan penamaan Huruf Kapital Kolom SQL View mu!
                            $depts = [
                                'PE' => 'PE_REMARK', 
                                'QC' => 'QC_REMARK', 
                                'MOLD' => 'MOLDSHOP_REMARK', 
                                'PPIC' => 'PPIC_REMARK', 
                                'PROD' => 'PRODUCTION_REMARK', 
                                'MKT' => 'MARKETING_REMARK'
                            ];
                            foreach($depts as $label => $col_name):
                            ?>
                            <div class="col-12 p-2 border-bottom">
                                <label class="small fw-bold text-muted"><?php echo $label; ?> REMARK</label>
                                <textarea name="<?php echo strtolower($label); ?>_remark" class="form-control form-control-sm mb-2" rows="2"><?php echo ($dataEdit && isset($dataEdit[$col_name])) ? trim($dataEdit[$col_name]) : ''; ?></textarea>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm bg-dark text-white">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3 small text-uppercase text-warning"><i class="bi bi-shield-check me-2"></i> Final Post Status</h6>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="x-small fw-bold text-white-50">PREPARED BY</label>
                                <input type="text" name="prepared" class="form-control form-control-sm bg-transparent text-white" value="<?php echo ($dataEdit && isset($dataEdit['IMC_PREPARED'])) ? rtrim($dataEdit['IMC_PREPARED']) : $_SESSION['erp_user']; ?>">
                            </div>
                            <div class="col-6">
                                <label class="x-small fw-bold text-white-50">STATUS</label>
                                <select name="status" class="form-select form-select-sm bg-warning fw-bold border-0">
                                    <?php while($s = sqlsrv_fetch_array($qStatus, SQLSRV_FETCH_ASSOC)): ?>
                                        <option value="<?php echo $s['STATUS']; ?>" <?php echo ($dataEdit && isset($dataEdit['STATUS']) && rtrim($dataEdit['STATUS']) == rtrim($s['STATUS'])) ? 'selected' : ''; ?>><?php echo $s['STATUS']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" name="btnSimpan" class="btn btn-primary w-100 fw-bold shadow-sm py-2"><i class="bi bi-save2 me-2"></i> <?php echo $id_edit > 0 ? 'UPDATE DATA (SAVE)' : 'SIMPAN PERUBAHAN (SAVE)'; ?></button>
                                <a href="?page=history" class="btn btn-outline-light w-100 mt-2 btn-sm border-0">BATAL (CANCEL)</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    $('#select_cust').on('change', function() {
        let custID = $(this).val();
        let target = $('#select_item');
        target.html('<option>Loading...</option>');
        if(custID) {
            $.get('api_get_items.php', { cust_id: custID }, function(data) {
                target.html(data);
            });
        } else {
            target.html('<option value="">-- Pilih Customer Dulu --</option>');
        }
    });
});
</script>