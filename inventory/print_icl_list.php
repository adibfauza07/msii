<?php
require_once __DIR__ . '/../config/database_p1.php';

// ==================================================================================
// DETEKSI AJAX: PENCARIAN SUPPLIER AUTO_COMPLETE
// ==================================================================================
if (isset($_POST['ajax_search_supplier'])) {
    while (ob_get_level()) ob_end_clean(); 
    header('Content-Type: application/json');
    
    $q = trim($_POST['q']);
    $res = [];
    
    $sql = "SELECT TOP 30 SUP_CODE, SUP_COMP FROM SUPPLIER WHERE SUP_CODE LIKE ? OR SUP_COMP LIKE ? ORDER BY SUP_COMP ASC";
    $stmt = sqlsrv_query($conn, $sql, array("%$q%", "%$q%"));
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $res[] = [
                'SUP_CODE' => trim((string)$row['SUP_CODE']),
                'SUP_COMP' => trim((string)$row['SUP_COMP'])
            ];
        }
    }
    echo json_encode($res);
    exit;
}

// Ambil parameter dari form / URL
// JIKA HALAMAN PERTAMA KALI DIBUKA (Belum ada parameter GET), SET DEFAULT TANGGAL 1 SAMPAI AKHIR BULAN BERJALAN
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$endDate   = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-t');
$supCode   = isset($_GET['sup_code']) ? trim($_GET['sup_code']) : '';
$supName   = isset($_GET['sup_name']) ? trim($_GET['sup_name']) : '';

// Karena tanggal sudah ada default-nya, halaman langsung dianggap ter-filter
$isFiltered = true; 

$dataList = [];
$periodeText = "Semua Waktu (All Time)";
$supplierText = "Semua Supplier";

// Jika Supplier Code dikosongkan tapi namanya terisi, cari kodenya dulu
if (!empty($supCode) && empty($supName)) {
    $qCari = sqlsrv_query($conn, "SELECT SUP_COMP FROM SUPPLIER WHERE SUP_CODE = ?", array($supCode));
    if ($qCari && $rCari = sqlsrv_fetch_array($qCari)) {
        $supName = $rCari['SUP_COMP'];
    }
}

// Proses Query Data Berdasarkan Filter Aktif
if ($isFiltered) {
    $conditions = ["T.TRTY_CODE IN ('01', '12')"];
    $params = array();

    if (!empty($startDate) && !empty($endDate)) {
        $conditions[] = "CAST(T.TRAN_DATE AS DATE) >= ?";
        $conditions[] = "CAST(T.TRAN_DATE AS DATE) <= ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $periodeText = date('d-M-Y', strtotime($startDate)) . " s/d " . date('d-M-Y', strtotime($endDate));
    } elseif (!empty($startDate)) {
        $conditions[] = "CAST(T.TRAN_DATE AS DATE) >= ?";
        $params[] = $startDate;
        $periodeText = "Mulai " . date('d-M-Y', strtotime($startDate));
    } elseif (!empty($endDate)) {
        $conditions[] = "CAST(T.TRAN_DATE AS DATE) <= ?";
        $params[] = $endDate;
        $periodeText = "Hingga " . date('d-M-Y', strtotime($endDate));
    }

    if (!empty($supCode)) {
        $conditions[] = "T.SUP_CODE = ?";
        $params[] = $supCode;
        $supplierText = !empty($supName) ? $supName : $supCode;
    }

    $whereClause = "WHERE " . implode(" AND ", $conditions);

    $sql = "SELECT T.TRAN_DOC, T.TRAN_DATE, S.SUP_COMP 
            FROM TRANS T 
            LEFT JOIN SUPPLIER S ON T.SUP_CODE = S.SUP_CODE
            $whereClause
            ORDER BY T.TRAN_DATE ASC, T.TRAN_DOC ASC";

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("<div style='color:red;'><b>Error Database:</b><br>" . print_r(sqlsrv_errors(), true) . "</div>");
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dataList[] = $row;
    }
}

$companyName = isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2' ? "PT. IMC TEKNO INDONESIA PLANT 2" : "PT. IMC TEKNO INDONESIA PLANT 1";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap ICL</title>
    <!-- Include jQuery untuk AJAX Autocomplete -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .container { width: 100%; max-width: 800px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2, .header h3 { margin: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
        th { background-color: #f2f2f2; text-align: center; font-weight: bold;}
        .text-center { text-align: center; }
        
        .no-print { text-align: center; margin-bottom: 20px; }
        .filter-box { 
            background: #f8f9fa; border: 1px solid #ddd; padding: 15px; 
            margin-bottom: 20px; border-radius: 5px; position: relative;
        }
        .filter-box form { 
            display: inline-flex; flex-wrap: wrap; gap: 12px; 
            align-items: center; justify-content: center; 
        }
        .filter-box label { font-weight: bold; font-size: 13px; }
        .filter-box input { 
            padding: 6px; border: 1px solid #ccc; font-size: 13px; border-radius: 3px;
        }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; border-radius: 3px; }
        .btn-primary { background: #007bff; color: white; border-color: #0056b3; }
        .btn-primary:hover { background: #0056b3; }

        /* CUSTOM AUTOCOMPLETE STYLE */
        .ac-box { position: absolute; z-index: 9999; background: #ffffff; color: #333; border: 1px solid #ccc; border-radius: 2px; max-height: 200px; overflow-y: auto; min-width: 300px; display: none; font-size: 12px; box-shadow: 2px 2px 5px rgba(0,0,0,0.2); text-align: left; }
        .ac-item { padding: 6px 10px; cursor: pointer; border-bottom: 1px solid #f4f4f4; }
        .ac-item:hover, .ac-item.active { background: #007bff; color: #ffffff; }
        
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

    <!-- FILTER AREA -->
    <div class="no-print filter-box">
        <form method="GET" action="">
            <label>Mulai:</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
            
            <label>Hingga:</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            
            <label>Supplier:</label>
            <div style="position: relative; display: inline-block;">
                <input type="hidden" name="sup_code" id="sup_code" value="<?php echo htmlspecialchars($supCode); ?>">
                <input type="text" id="supSearchInput" name="sup_name" value="<?php echo htmlspecialchars($supName); ?>" placeholder="Ketik Kode / Nama Supplier..." autocomplete="off" oninput="showSupplierAC(this)" onkeydown="supplierKey(event, this)" style="width: 280px;">
            </div>
            
            <button type="submit" class="btn btn-primary">Terapkan Filter</button>
            <button type="button" class="btn" onclick="window.location.href='print_icl_list.php'">Reset</button>
        </form>
    </div>

    <!-- Kotak Melayang Autocomplete -->
    <div id="acBox" class="ac-box"></div>

    <!-- ACTION BUTTONS -->
    <div class="no-print">
        <button class="btn" onclick="window.print()">Print Report</button>
        <a href="export_icl_list.php?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&sup_code=<?php echo urlencode($supCode); ?>" class="btn" style="text-decoration:none; color:black;">Export to Excel</a>
        <button class="btn" onclick="window.close()">Tutup</button>
    </div>

    <div class="container">
        <div class="header">
            <h2><?php echo $companyName; ?></h2>
            <h3>REKAP LIST INCOMING CHECK LIST (ICL)</h3>
            <p>
                Periode: <b><?php echo $periodeText; ?></b><br>
                Supplier: <b><?php echo htmlspecialchars($supplierText); ?></b>
            </p>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 15%;">Tanggal</th>
                    <th style="width: 30%;">Nomor ICL</th>
                    <th style="width: 50%;">Nama Supplier</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (empty($dataList)) {
                    echo "<tr><td colspan='4' class='text-center' style='padding: 20px; color: red;'>Tidak ada data ICL pada filter yang dipilih.</td></tr>";
                } else {
                    $no = 1;
                    foreach ($dataList as $row) {
                        $tgl = ($row['TRAN_DATE'] instanceof DateTime) ? $row['TRAN_DATE']->format('d-m-Y') : $row['TRAN_DATE'];
                        $sup = !empty($row['SUP_COMP']) ? $row['SUP_COMP'] : '-';
                        
                        echo "<tr>
                                <td class='text-center'>{$no}</td>
                                <td class='text-center'>{$tgl}</td>
                                <td style='font-weight:bold;'>{$row['TRAN_DOC']}</td>
                                <td>{$sup}</td>
                              </tr>";
                        $no++;
                    }
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- SCRIPT AUTOCOMPLETE SUPPLIER -->
    <script>
    var acBox = null, acItems = [], acIndex = -1;

    function byId(id) { return document.getElementById(id); }
    function initAC() { acBox = byId("acBox"); }
    function hideAC() {
        if(acBox){ acBox.style.display="none"; acBox.innerHTML=""; }
        acItems = []; acIndex = -1;
    }
    function positionAC(input) {
        initAC(); var r = input.getBoundingClientRect();
        acBox.style.left = (r.left + (window.scrollX || window.pageXOffset)) + "px";
        acBox.style.top = (r.bottom + (window.scrollY || window.pageYOffset)) + "px";
        acBox.style.width = (r.width < 320 ? 320 : r.width) + "px";
    }
    function renderAC(renderText, pickFunc) {
        initAC(); acBox.innerHTML="";
        for(var i=0; i<acItems.length; i++){
            var d = document.createElement("div");
            d.className = "ac-item" + (i == acIndex ? " active" : "");
            d.innerHTML = renderText(acItems[i]);
            d.setAttribute("data-index", i);
            d.onmousedown = function(){ pickFunc(acItems[parseInt(this.getAttribute("data-index"), 10)]); };
            acBox.appendChild(d);
        }
        acBox.style.display = acItems.length > 0 ? "block" : "none";
    }
    function acMove(step, renderText, pickFunc){
        if(acItems.length <= 0) return;
        acIndex += step;
        if(acIndex < 0) acIndex = acItems.length - 1;
        if(acIndex >= acItems.length) acIndex = 0;
        renderAC(renderText, pickFunc);
    }
    function acEnter(pickFunc){
        if(acItems.length <= 0) return false;
        if(acIndex < 0) acIndex = 0;
        pickFunc(acItems[acIndex]);
        return true;
    }

    $(document).on("click", function(e) {
        if(acBox && !acBox.contains(e.target) && e.target.id !== 'supSearchInput') { hideAC(); }
    });

    function showSupplierAC(input) {
        initAC();
        var key = (input.value || "").trim();
        acItems = []; acIndex = -1;
        
        if(key.length < 1){ 
            $('#sup_code').val('');
            hideAC(); 
            return; 
        }

        $.ajax({
            url: window.location.href,
            type: 'POST',
            dataType: 'json',
            data: { ajax_search_supplier: 1, q: key },
            success: function(res) {
                if(res && res.length > 0) {
                    acItems = res;
                    positionAC(input);
                    renderAC(function(o){ return "<b>" + o.SUP_CODE + "</b> - " + o.SUP_COMP; }, pickSupplier);
                } else {
                    $('#sup_code').val('');
                    hideAC();
                }
            }
        });
    }

    function supplierKey(e, input) {
        if(e.key === "ArrowDown") {
            e.preventDefault(); 
            if(acItems.length == 0) showSupplierAC(input);
            acMove(1, function(o){ return "<b>" + o.SUP_CODE + "</b> - " + o.SUP_COMP; }, pickSupplier); 
            return false;
        }
        if(e.key === "ArrowUp") {
            e.preventDefault(); 
            if(acItems.length == 0) showSupplierAC(input);
            acMove(-1, function(o){ return "<b>" + o.SUP_CODE + "</b> - " + o.SUP_COMP; }, pickSupplier); 
            return false;
        }
        if(e.key === "Enter") {
            if(acItems.length > 0 && acIndex >= 0) { 
                e.preventDefault(); 
                acEnter(pickSupplier); 
                return false; 
            }
        }
        if(e.key === "Escape") hideAC();
    }

    function pickSupplier(o) {
        $('#sup_code').val(o.SUP_CODE);
        $('#supSearchInput').val(o.SUP_COMP);
        hideAC();
    }
    </script>
</body>
</html>