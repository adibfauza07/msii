<?php
// ==========================================
// FILE: ogr.php
// KOMPATIBEL: PHP 5.4
// DESAIN: Multi-Tabs (61 Sheets) + Visual PDF Layout
// ==========================================

// 1. KONEKSI DATABASE
require_once __DIR__ . '/../config/global.php';

if ($conn === false) {
    die("<div style='color:red; font-weight:bold; padding:20px;'>Koneksi database tidak tersedia. Cek file global.php.</div>");
}

// ==========================================
// 2. PROSES SIMPAN DATA (POST)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $params = array();

    // Menangkap Tab/Sheet mana yang sedang disubmit
    $sheet_name = isset($_POST['sheet_name']) ? $_POST['sheet_name'] : 'Unknown Sheet';
    $params[] = $sheet_name;

    // --- Header & Appearance ---
    $params[] = isset($_POST['part_name']) ? $_POST['part_name'] : '';
    $params[] = isset($_POST['customer']) ? $_POST['customer'] : '';
    $params[] = isset($_POST['machine_no']) ? $_POST['machine_no'] : '';
    $params[] = isset($_POST['part_no']) ? $_POST['part_no'] : '';
    $params[] = isset($_POST['production_date']) ? $_POST['production_date'] : '';
    $params[] = isset($_POST['cavity_info']) ? $_POST['cavity_info'] : '';
    $params[] = isset($_POST['material']) ? $_POST['material'] : '';
    $params[] = (isset($_POST['lot_qty']) && trim($_POST['lot_qty']) !== '') ? $_POST['lot_qty'] : null;

    // Appearance (10 Poin: A sampai J)
    $params[] = isset($_POST['app_a']) ? $_POST['app_a'] : 'OK';
    $params[] = isset($_POST['app_b']) ? $_POST['app_b'] : 'OK';
    $params[] = isset($_POST['app_c']) ? $_POST['app_c'] : 'OK';
    $params[] = isset($_POST['app_d']) ? $_POST['app_d'] : 'OK';
    $params[] = isset($_POST['app_e']) ? $_POST['app_e'] : 'OK';
    $params[] = isset($_POST['app_f']) ? $_POST['app_f'] : 'OK';
    $params[] = isset($_POST['app_g']) ? $_POST['app_g'] : 'OK';
    $params[] = isset($_POST['app_h']) ? $_POST['app_h'] : 'OK';
    $params[] = isset($_POST['app_i']) ? $_POST['app_i'] : 'OK';
    $params[] = isset($_POST['app_j']) ? $_POST['app_j'] : 'OK';

    // --- Menyusun 168 Kolom Dimensi (4 Cavity x 3 Shift x 14 Poin) ---
    $dimColumns = array();
    $dimPlaceholders = array();
    
    for ($c = 1; $c <= 4; $c++) {
        for ($s = 1; $s <= 3; $s++) {
            for ($p = 1; $p <= 14; $p++) {
                $colName = "Dim_C{$c}_S{$s}_P{$p}";
                $dimColumns[] = $colName;
                $dimPlaceholders[] = "?"; 
                
                $postKey = strtolower($colName);
                $postValue = isset($_POST[$postKey]) ? trim($_POST[$postKey]) : '';
                
                $params[] = ($postValue !== '') ? (float)$postValue : null;
            }
        }
    }

    $dimColString = implode(", ", $dimColumns);
    $dimPlaceholdersString = implode(", ", $dimPlaceholders);

    // --- Sisa Parameter Bawah ---
    array_push($params, 
        isset($_POST['inspector_1']) ? $_POST['inspector_1'] : '',
        isset($_POST['inspector_2']) ? $_POST['inspector_2'] : '',
        isset($_POST['inspector_3']) ? $_POST['inspector_3'] : '',
        isset($_POST['final_judgement']) ? $_POST['final_judgement'] : 'OK'
    );

    // Menyusun Query (Ditambahkan kolom SheetName di depan)
    $sql = "INSERT INTO OutGoingRecord (
                SheetName, PartName, Customer, MachineNo, PartNo, ProductionDate, CavityInfo, Material, LotQty, 
                App_A, App_B, App_C, App_D, App_E, App_F, App_G, App_H, App_I, App_J, 
                $dimColString, 
                Inspector1, Inspector2, Inspector3, FinalJudgement
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                $dimPlaceholdersString, 
                ?, ?, ?, ?
            )";
            
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errorMsg = isset($errors[0]['message']) ? addslashes($errors[0]['message']) : 'Unknown error';
        echo "<script>alert('Error Simpan di Tab $sheet_name : " . $errorMsg . "');</script>";
    } else {
        echo "<script>alert('Data OGR untuk tab $sheet_name berhasil tersimpan!'); window.location.href='';</script>";
    }
}

// ==========================================
// 3. PERSIAPAN DATA TAB & LAYOUT
// ==========================================
$sample_tabs = array();
for ($i = 1; $i <= 30; $i++) {
    $sample_tabs[] = "sampel {$i}(1)";
    $sample_tabs[] = "sampel {$i}(2)";
}

$points = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14);
$stds   = array("3.575", "4.5", "P = 0.5", "3", "11.65", "1.075", "6.8", "3.2", "p = 0.5", "4.5", "0.33", "2.4", "3.7", "3.4");
$maxs   = array("0.03", "0.04", "0.02", "0.05", "0.01", "0.03", "0.04", "0", "0.02", "0.04", "0.01", "0.02", "0.01", "0.02");
$mins   = array("-0.03", "-0.04", "-0.02", "-0.05", "-0.04", "-0.03", "-0.04", "-0.05", "-0.02", "-0.04", "-0.01", "-0.04", "-0.04", "-0.02");
$tolMax = array("3.605", "4.540", "0.520", "3.050", "11.660", "1.105", "6.840", "3.200", "0.520", "4.540", "0.340", "2.420", "3.710", "3.420");
$tolMin = array("3.545", "4.460", "0.480", "2.950", "11.610", "1.045", "6.760", "3.150", "0.480", "4.460", "0.320", "2.360", "3.660", "3.380");

$items_appearance = array(
    "A" => "No Short Mold", "B" => "No Burry", "C" => "No Broken", 
    "D" => "Housing Close Hole", "E" => "No Crack", "F" => "No Mismatch",
    "G" => "No Step", "H" => "No Weldline", "I" => "No Burning", "J" => "No Shinkmark"
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>OGR Form Multi-Tabs</title>
    <!-- Bootstrap 5 untuk Nav Tabs -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Arial Narrow', Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; background-color: #e9ecef;}
        .excel-container { width: 100%; max-width: 1500px; margin: 0 auto; background-color: #fff; padding: 15px; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        
        /* Desain Tabs ala Excel */
        .nav-tabs .nav-link { font-size: 12px; font-weight: bold; color: #555; border-radius: 0; padding: 5px 15px; border: 1px solid #ccc; margin-right: 2px; background: #f8f9fa; white-space: nowrap;}
        .nav-tabs .nav-link.active { background-color: #fff; border-bottom-color: transparent; color: #000; border-top: 3px solid #28a745; }
        .tab-content { border: 1px solid #ccc; border-top: none; padding: 15px; background: #fff; overflow-x: auto; }
        
        /* Desain Form Tabel Visual PDF */
        table.tbl-qc { width: 100%; border-collapse: collapse; margin-bottom: 5px; min-width: 1100px; }
        table.tbl-qc th, table.tbl-qc td { border: 1px solid #000; padding: 1px 3px; text-align: center; vertical-align: middle; height: 20px; }
        
        .text-left { text-align: left !important; }
        .text-bold { font-weight: bold; }
        .bg-yellow { background-color: #ffff00; font-weight: bold; }
        .bg-light-gray { background-color: #f4f4f4; }
        
        input.qc-input, select.qc-select { 
            width: 100%; height: 100%; box-sizing: border-box; 
            border: none; outline: none; background: transparent; 
            font-family: inherit; font-size: inherit; text-align: center; 
        }
        input.qc-input:focus, select.qc-select:focus { background-color: #e6f7ff; }
        .input-left { text-align: left !important; padding-left: 5px; }
        
        .header-title { font-size: 12px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase;}
        .checkbox-group { margin-bottom: 10px; font-size: 11px; font-weight: bold; }
        
        .middle-section { display: table; width: 100%; margin-bottom: 5px; min-width: 1100px; }
        .drawing-area { display: table-cell; width: 60%; border: 1px solid #000; vertical-align: middle; text-align: center; color: #888; }
        .appearance-area { display: table-cell; width: 40%; vertical-align: top; padding-left: 5px; }
        
        .btn-save { background-color: #28a745; color: white; border: 1px solid #1e7e34; padding: 8px 20px; font-size: 14px; font-weight: bold; cursor: pointer; float: right; margin-top: 10px; }
        .btn-save:hover { background-color: #218838; }
    </style>
</head>
<body>

<div class="excel-container">
    
    <!-- BAGIAN NAVIGASI TABS (DAFTAR ISI + 60 SAMPEL) -->
    <ul class="nav nav-tabs flex-nowrap overflow-auto" id="excelTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="daftar-isi-tab" data-bs-toggle="tab" data-bs-target="#daftar-isi" type="button" role="tab">DAFTAR ISI</button>
        </li>
        <?php foreach ($sample_tabs as $index => $tab_name): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-<?php echo $index; ?>" data-bs-toggle="tab" data-bs-target="#content-<?php echo $index; ?>" type="button" role="tab">
                <?php echo strtoupper($tab_name); ?>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- BAGIAN KONTEN TABS -->
    <div class="tab-content" id="excelTabsContent">
        
        <!-- Tab: DAFTAR ISI -->
        <div class="tab-pane fade show active" id="daftar-isi" role="tabpanel">
            <div style="padding: 50px 20px;">
                <h3 class="fw-bold">DAFTAR ISI</h3>
                <h5 class="text-secondary">DATA OUTGOING MATERIAL</h5>
                <h5 class="text-secondary">DEPARTEMEN QUALITY CONTROL</h5>
                <hr>
                <p>Silakan klik tab <b>SAMPEL</b> di atas (bisa di-scroll / geser ke kanan) untuk mulai mengisi form OGR.</p>
                <p class="text-danger fw-bold"><i class="bi bi-info-circle"></i> Perhatian: Simpan (Submit) form pada masing-masing tab sebelum berpindah ke tab lain agar data tidak hilang.</p>
            </div>
        </div>
        
        <!-- Tab: 60 SAMPEL (LOOPING FORM) -->
        <?php foreach ($sample_tabs as $index => $tab_name): ?>
        <div class="tab-pane fade" id="content-<?php echo $index; ?>" role="tabpanel">
            
            <form method="POST" action="">
                <!-- Identifikasi Sheet Name untuk PHP -->
                <input type="hidden" name="sheet_name" value="<?php echo htmlspecialchars($tab_name); ?>">

                <div class="header-title">PT. IMC TEKNO INDONESIA<br>QC DEPARTEMENT</div>
                <div class="checkbox-group">
                    <label><input type="checkbox" checked onclick="return false;"> ☑ OUT GOING RECORD [ <?php echo strtoupper($tab_name); ?> ]</label>
                    <label><input type="checkbox" checked onclick="return false;"> ☑ DIMENSION CHECK SHEET</label>
                </div>

                <!-- TABEL 1: HEADER INFORMASI -->
                <table class="tbl-qc">
                    <tr>
                        <td width="10%" class="text-left text-bold">Part Name</td>
                        <td width="30%" class="text-left"><input type="text" name="part_name" class="qc-input input-left text-bold" value="FX231-20P-055SVIOP HOUSING 10"></td>
                        <td width="10%" class="text-left text-bold">Customer</td>
                        <td width="20%" class="text-left"><input type="text" name="customer" class="qc-input input-left" value="PT. HIROSE"></td>
                        <td width="10%" class="text-left text-bold">Machine No.</td>
                        <td width="10%"><input type="text" name="machine_no" class="qc-input" value="02"></td>
                        <td width="10%" class="text-bold bg-light-gray">Checked</td>
                    </tr>
                    <tr>
                        <td class="text-left text-bold">Part No.</td>
                        <td class="text-left"><input type="text" name="part_no" class="qc-input input-left text-bold" value="DP-159077-0-11 41"></td>
                        <td class="text-left text-bold">Production Date</td>
                        <td class="text-left"><input type="text" name="production_date" class="qc-input input-left" value="1251209B021/1251209C022"></td>
                        <td class="text-left text-bold">Cavity (Std. & Actual)</td>
                        <td><input type="text" name="cavity_info" class="qc-input" value="1/1"></td>
                        <td rowspan="2"></td>
                    </tr>
                    <tr>
                        <td class="text-left text-bold">Mat'l Grade & Colour</td>
                        <td class="text-left"><input type="text" name="material" class="qc-input input-left" value="PA9T KURARAY GENESTAR GN2330-1 / BLACK"></td>
                        <td colspan="2" class="text-left"><input type="text" class="qc-input input-left" value="<?php echo date('Y/m/d'); ?>" readonly></td>
                        <td class="text-left text-bold">Lot Qty.</td>
                        <td><input type="number" name="lot_qty" class="qc-input"></td>
                    </tr>
                </table>

                <!-- TABEL 2: TENGAH (DRAWING & APPEARANCE) -->
                <div class="middle-section">
                    <div class="drawing-area">
                        <br><br><i>[ Area Referensi Drawing - <?php echo strtoupper($tab_name); ?> ]</i><br><br>
                        <div style="text-align: left; font-size: 9px; padding-left:5px; margin-top:30px;"><i>TOO DETAIL PLEASE SEE ORIGINAL DRAWING</i></div>
                    </div>
                    <div class="appearance-area">
                        <table class="tbl-qc" style="margin-bottom: 0;">
                            <tr><td colspan="4" class="text-bold bg-light-gray">APPEARANCE CHECK</td></tr>
                            <tr>
                                <td width="10%" class="text-bold">No.</td>
                                <td width="60%" class="text-bold">Item</td>
                                <td width="15%" class="text-bold">Equipment</td>
                                <td width="15%" class="text-bold">Result</td>
                            </tr>
                            <?php foreach($items_appearance as $kode => $item): ?>
                            <tr>
                                <td><?php echo $kode; ?></td>
                                <td class="text-left"><?php echo $item; ?></td>
                                <td>Visual</td>
                                <td>
                                    <select name="app_<?php echo strtolower($kode); ?>" class="qc-select">
                                        <option value="OK">OK</option>
                                        <option value="NG">NG</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>

                <!-- TABEL 3: DIMENSION CHECK (168 KOLOM) -->
                <table class="tbl-qc">
                    <tr>
                        <td colspan="2" rowspan="2" class="bg-light-gray text-bold" style="width:50px;">Point</td>
                        <?php foreach($points as $p) echo "<td class='text-bold bg-light-gray'>$p</td>"; ?>
                        <td rowspan="7" width="4%" class="text-bold bg-light-gray" style="writing-mode: vertical-rl; transform: rotate(180deg);">Decision</td>
                    </tr>
                    <tr>
                        <?php foreach($stds as $v) echo "<td class='bg-light-gray text-bold'>$v</td>"; ?>
                    </tr>
                    <tr>
                        <td colspan="2" rowspan="2" class="bg-light-gray">Specification</td>
                        <?php foreach($maxs as $v) echo "<td>$v</td>"; ?>
                    </tr>
                    <tr>
                        <?php foreach($mins as $v) echo "<td>$v</td>"; ?>
                    </tr>
                    <tr>
                        <td colspan="2" class="text-left text-bold bg-yellow">Tolerance Max</td>
                        <?php foreach($tolMax as $v) echo "<td class='bg-yellow'>$v</td>"; ?>
                    </tr>
                    <tr>
                        <td colspan="2" class="text-left text-bold bg-yellow">Tolerance Min</td>
                        <?php foreach($tolMin as $v) echo "<td class='bg-yellow'>$v</td>"; ?>
                    </tr>
                    
                    <!-- Looping 4 Cavity x 3 Shift -->
                    <?php for($c = 1; $c <= 4; $c++): ?>
                        <?php for($s = 1; $s <= 3; $s++): ?>
                        <tr>
                            <?php if($s == 1): ?>
                                <td rowspan="3" width="2%" class="text-bold bg-light-gray"><?php echo $c; ?></td>
                            <?php endif; ?>
                            <td width="5%" class="bg-light-gray text-left" style="padding-left:3px; white-space:nowrap;">Shift <?php echo $s; ?></td>
                            
                            <?php for($p = 1; $p <= 14; $p++): ?>
                                <?php 
                                    $idx = $p - 1; 
                                    $min = $tolMin[$idx];
                                    $max = $tolMax[$idx];
                                ?>
                                <td>
                                    <input type="number" step="0.001" 
                                           name="dim_c<?php echo $c; ?>_s<?php echo $s; ?>_p<?php echo $p; ?>" 
                                           data-min="<?php echo $min; ?>" 
                                           data-max="<?php echo $max; ?>"
                                           class="qc-input dim-input"
                                           style="font-size:12px;">
                                </td>
                            <?php endfor; ?>
                        </tr>
                        <?php endfor; ?>
                    <?php endfor; ?>

                    <!-- FOOTER DECISION -->
                    <?php 
                    $romawis = array("I", "II", "III");
                    foreach($romawis as $romawi): 
                    ?>
                    <tr>
                        <td colspan="2" class="text-bold bg-light-gray text-left" style="padding-left:5px;">Decision <?php echo $romawi; ?></td>
                        <?php for($p = 1; $p <= 14; $p++): ?>
                            <td><input type="text" class="qc-input" value="0" readonly style="color:#aaa;"></td>
                        <?php endfor; ?>
                        <td class="text-bold">0</td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- BARIS CATATAN & JUDGEMENT BAWAH -->
                    <tr>
                        <td colspan="4" rowspan="2" class="text-left text-bold bg-light-gray" style="vertical-align: top; padding:5px;">
                            Initial Check Point<br><br>O = OK<br>X = NG
                        </td>
                        <td colspan="6" rowspan="2" class="text-left bg-light-gray" style="vertical-align: top; padding:5px;">
                            <span class="text-bold">Note Shift 2 :</span><br>
                            <textarea style="width:100%; height:40px; border:none; background:transparent; outline:none; font-family:inherit; resize:none;"></textarea>
                        </td>
                        <td colspan="6" rowspan="2" class="text-left bg-light-gray" style="vertical-align: top; padding:5px;">
                            <span class="text-bold">Note Shift 3 :</span><br>
                            <textarea style="width:100%; height:40px; border:none; background:transparent; outline:none; font-family:inherit; resize:none;"></textarea>
                        </td>
                        <td class="text-bold bg-light-gray">Final Judgement</td>
                    </tr>
                    <tr>
                        <td style="font-size: 20px; font-weight: bold; height:45px;">
                            <select name="final_judgement" class="qc-select" style="font-size: 22px; font-weight: bold; color: green; cursor:pointer;">
                                <option value="OK">OK</option>
                                <option value="NG" style="color: red;">NG</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <!-- BAGIAN TANDA TANGAN (INSPECTOR) -->
                <table class="tbl-qc" style="margin-top:5px; border:none;">
                    <tr>
                        <td width="25%" class="text-left bg-light-gray" style="border:none;">Inspector 1 : <input type="text" name="inspector_1" class="qc-input input-left" style="width:100px; border-bottom:1px solid #000;"></td>
                        <td width="25%" class="text-left bg-light-gray" style="border:none;">Inspector 2 : <input type="text" name="inspector_2" class="qc-input input-left" style="width:100px; border-bottom:1px solid #000;"></td>
                        <td width="25%" class="text-left bg-light-gray" style="border:none;">Inspector 3 : <input type="text" name="inspector_3" class="qc-input input-left" style="width:100px; border-bottom:1px solid #000;"></td>
                        <td width="25%" class="text-left bg-light-gray" style="border:none;">Remarks : <input type="text" class="qc-input input-left" style="width:120px; border-bottom:1px solid #000;"></td>
                    </tr>
                </table>

                <button type="submit" class="btn-save"><i class="bi bi-save"></i> SUBMIT OGR (<?php echo strtoupper($tab_name); ?>)</button>
                <div style="clear:both;"></div>
            </form>
            
        </div>
        <?php endforeach; ?>
        
    </div>
</div>

<!-- Script JS (Bootstrap Tab & Validasi Warna) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var inputs = document.querySelectorAll('.dim-input');
    
    // Looping klasik IE/PHP lama support
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].addEventListener('input', function() {
            var val = parseFloat(this.value);
            var min = parseFloat(this.getAttribute('data-min'));
            var max = parseFloat(this.getAttribute('data-max'));
            
            if (this.value === '') {
                this.style.backgroundColor = '';
                this.style.color = '';
                this.style.fontWeight = '';
            } else if (val < min || val > max) {
                this.style.backgroundColor = '#ffcccc'; // Merah NG
                this.style.color = '#cc0000';
                this.style.fontWeight = 'bold';
            } else {
                this.style.backgroundColor = '#ccffcc'; // Hijau OK
                this.style.color = '#006600';
                this.style.fontWeight = 'bold';
            }
        });
    }
});
</script>

</body>
</html>