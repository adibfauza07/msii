<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label fw-bold">Tanggal</label>
        <input type="date" name="DATE" id="DATE" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-bold">Operation Method</label>
        <select name="OPERATION" id="OPERATION" class="form-select">
            <option value="MANUAL">MANUAL</option>
            <option value="AUTO ROBOT">AUTO ROBOT</option>
            <option value="SEMI AUTO">SEMI AUTO</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-bold">Regrind Material (%)</label>
        <input type="number" name="REGRIND_PCT" id="REGRIND_PCT" class="form-control" value="0">
    </div>

    <div class="col-12 mt-4">
        <div class="card border-info">
            <div class="card-header bg-info text-white fw-bold small">QUALITY APPEARANCE CHECKLIST (V=OK, X=NG)</div>
            <div class="card-body p-2">
                <div class="row text-center small">
                    <?php 
                    $checks = [
                        'CHK_BURRY' => 'No Burry', 'CHK_VOID' => 'No Void', 'CHK_SHORTMOLD' => 'No Shortmold',
                        'CHK_WELDLINE' => 'No Weld Line', 'CHK_BURNING' => 'No Burning', 'CHK_SINKMARK' => 'No Sink Mark',
                        'CHK_DENTED' => 'No Dented', 'CHK_SILVER' => 'No Silver Mark', 'CHK_SCRATCH' => 'No Scratch'
                    ];
                    foreach($checks as $key => $label): ?>
                    <div class="col">
                        <label><?= $label ?></label>
                        <select name="<?= $key ?>" id="<?= $key ?>" class="form-select form-select-sm">
                            <option value="V">V</option>
                            <option value="X">X</option>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mt-3">
        <label class="small fw-bold">Foto Material</label>
        <input type="file" name="foto_material" class="form-control form-control-sm">
    </div>
    <div class="col-md-4 mt-3">
        <label class="small fw-bold">Foto Mold (Core)</label>
        <input type="file" name="foto_mold_core" class="form-control form-control-sm">
    </div>
    <div class="col-md-4 mt-3">
        <label class="small fw-bold">Foto Mold (Cavity)</label>
        <input type="file" name="foto_mold_cavity" class="form-control form-control-sm">
    </div>
</div>