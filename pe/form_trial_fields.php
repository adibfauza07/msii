<div class="col-md-3">
  <label>Kode Trial (Auto)</label>
  <input type="text" name="TRIAL_CODE" class="form-control" readonly value="<?php echo $autoTrialCode; ?>">
</div>

<div class="col-md-3">
  <label>Tanggal</label>
  <input type="date" name="DATE" class="form-control" required>
</div>

<div class="col-md-3">
  <label>Kode Part</label>
  <input type="text" name="PART_CODE" id="PART_CODE" class="form-control" placeholder="Ketik kode part..." autocomplete="off">
</div>

<div class="col-md-3">
  <label>Nama Part</label>
  <input type="text" id="PART_NAME" class="form-control" readonly>
</div>

<div class="col-md-3">
  <label>Customer</label>
  <input type="text" id="CUST_COMP" class="form-control" readonly>
  <input type="hidden" name="CUST_ID" id="CUST_ID">
</div>

<div class="col-md-3">
  <label>Material</label>
  <input type="hidden" name="MAT_USING" id="MAT_USING"> <!-- ID material -->
  <input type="text" id="MAT_NAME" class="form-control" readonly> <!-- nama material -->
</div>

<div class="col-md-3">
  <label>Qty Trial</label>
  <input type="number" name="QUANTITY_TRIAL" class="form-control">
</div>

<div class="col-md-3">
  <label>Alasan Trial</label>
  <input type="text" name="TRIAL_REASON" class="form-control">
</div>

<div class="col-md-3">
  <label>Trial Ke</label>
  <input type="number" name="TRIAL_TIMES" class="form-control">
</div>

<div class="col-md-3">
  <label>Drying Time (menit)</label>
  <input type="number" name="MAT_DRYING_TIME" class="form-control">
</div>

<div class="col-md-3">
  <label>Mold Set Up (menit)</label>
  <input type="number" name="MOLD_SET_UP" class="form-control">
</div>

<div class="col-md-3">
  <label>Mold Set Down (menit)</label>
  <input type="number" name="MOLD_SET_DOWN" class="form-control">
</div>

<div class="col-md-3">
  <label>Durasi Trial (menit)</label>
  <input type="text" name="TRIAL_DURATION" class="form-control" placeholder="Contoh: 45 menit" required>
</div>

<div class="col-md-6">
  <label>QE Comment</label>
  <textarea name="QE_COMMENT" class="form-control" placeholder="Catatan dari Quality Engineer..." rows="2"></textarea>
</div>

<div class="col-md-6">
  <label>PE Comment</label>
  <textarea name="PE_COMMENT" class="form-control" placeholder="Catatan dari Product Engineer..." rows="2"></textarea>
</div>

<div class="col-md-3">
  <label>PIC</label>
  <input type="text" name="PIC" class="form-control">
</div>

<div class="col-md-3">
  <label>Weight Runner</label>
  <input type="text" name="WEIGHT_RUNNER" class="form-control">
</div>

<div class="col-md-3">
  <label>Prepared By</label>
  <input type="text" name="PREPARED" class="form-control">
</div>

<div class="col-md-3">
  <label>Checked By</label>
  <input type="text" name="CHECKED" class="form-control">
</div>

<div class="col-md-3">
  <label>Approved By</label>
  <input type="text" name="APPROVED" class="form-control">
</div>

<div class="col-md-3">
  <label>Qty OK</label>
  <input type="number" name="QTY_OK" class="form-control">
</div>

<div class="col-md-3">
  <label>Qty NG</label>
  <input type="number" name="QTY_NG" class="form-control">
</div>

<div class="col-md-3">
  <label>Cycle Time Actual</label>
  <input type="text" name="CYCLE_TIME_ACT" class="form-control">
</div>

<div class="col-md-3">
  <label>No Mesin</label>
  <input type="text" name="MAC_NO" class="form-control">
</div>

<div class="col-md-3">
  <label>Jenis Trial</label>
  <select name="JENIS_ID" class="form-select">
    <option value="">-- Pilih Jenis Trial --</option>
    <?php foreach($jenis_list as $j){ echo "<option value='{$j['ID']}'>{$j['JENIS_TRIAL']}</option>"; } ?>
  </select>
</div>

<div class="col-md-3">
  <label>Tonnage</label>
  <input type="text" name="TONAGE" class="form-control">
</div>

<div class="col-md-6">
  <label>Corrective Action</label>
  <textarea name="CORRECTIVE_ACTION" class="form-control" placeholder="Tulis langkah perbaikan secara detail..."></textarea>
</div>

<div class="col-md-6">
  <label>Analisis</label>
  <textarea name="ANALYSYS" class="form-control" placeholder="Catatan hasil analisis atau kesimpulan..."></textarea>
</div>

<div class="col-md-6">
  <label>Judge</label>
  <select name="JUDGE_ID" class="form-select form-select-lg">
    <option value="">-- Pilih Hasil Trial --</option>
    <?php foreach($judge_list as $j):
      $color="black";
      if (stripos($j['JUDGE_TRIAL'],"OK")!==false)$color="green";
      elseif(stripos($j['JUDGE_TRIAL'],"NG")!==false)$color="red";
      elseif(stripos($j['JUDGE_TRIAL'],"RE")!==false)$color="orange";
    ?>
      <option value="<?= $j['ID'] ?>" style="color:<?= $color ?>;font-weight:bold;">
        <?= htmlspecialchars($j['JUDGE_TRIAL']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

<div class="col-md-6">
  <label>Upload Foto (opsional)</label>
  <input type="file" name="foto" class="form-control" accept="image/*">
</div>
