<?php
if (session_id() == "") {
    session_start();
}

// Menyesuaikan dengan koneksi database di folder ppic
require_once __DIR__ . "/../config/database_ppic.php";

if (!$conn) {
    die("Koneksi database gagal.");
}

function h($value) {
    if ($value instanceof DateTime) {
        return htmlspecialchars($value->format('j-M-y'), ENT_QUOTES, "UTF-8");
    }
    if ($value === null) {
        return "";
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Helper untuk menghitung jumlah hari
function calculateDays($start, $end) {
    if ($start instanceof DateTime && $end instanceof DateTime) {
        $diff = $start->diff($end);
        return $diff->days + 1; // +1 karena dihitung inklusif (termasuk hari pertama)
    }
    return 0;
}

$defaultFrom = date('Y-m-01');
$defaultTo   = date('Y-m-t');

$fromDate    = isset($_GET['from_date']) ? $_GET['from_date'] : $defaultFrom;
$toDate      = isset($_GET['to_date']) ? $_GET['to_date'] : $defaultTo;
$selectedMac = isset($_GET['mac_code']) ? trim($_GET['mac_code']) : '';

$startSql = $fromDate . ' 00:00:00.000';
$endSql   = $toDate . ' 23:59:59.997';

// 1. Ambil Data STD_PACK, Maker, dan Warna Label 
// MAT_NAME di sini tetap diambil jaga-jaga tapi yang dipakai nanti yang dari SP
$sqlStdPack = "SELECT STD_PACK.ITEM_CODE, STD_PACK.STD_PACK, STD_PACK.STD_PACK_BOX, STD_PACK.MAT_NAME, STD_PACK.MAT_CODE, Mat_Maker.MAKER, STD_PACK.WARNA_LABEL
               FROM STD_PACK INNER JOIN
                    Mat_Maker ON STD_PACK.MAT_CODE = Mat_Maker.MAT_CODE";
$stmtStdPack = sqlsrv_query($conn, $sqlStdPack);

$stdPackData = array();
if ($stmtStdPack !== false) {
    while ($row = sqlsrv_fetch_array($stmtStdPack, SQLSRV_FETCH_ASSOC)) {
        $stdPackData[trim($row['ITEM_CODE'])] = $row;
    }
    sqlsrv_free_stmt($stmtStdPack);
}

// 2. Ambil Data Utama dari Stored Procedure yang SUDAH DIUPDATE (ada tambahan kolom MAT_NAME dari BOM)
$sql = "SET NOCOUNT ON; EXEC dbo.sp_mc_run_ppic_prod ?, ?";
$params = array($startSql, $endSql);
$stmt = sqlsrv_query($conn, $sql, $params);

$errorMsg = "";
if ($stmt === false) {
    $errors = sqlsrv_errors();
    foreach ($errors as $error) {
        $errorMsg .= $error['message'] . "<br>";
    }
}

// Mengambil data, mengumpulkan daftar semua mesin, dan melakukan grouping
$allMachines = array();
$groupedData = array();
$seenWoKeys  = array(); // Array untuk tracking dan mencegah data duplikat

if ($stmt !== false) {
    do {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $macCode = trim($row['mac_code']);
            if ($macCode === "") $macCode = "Tanpa Mesin";
            
            // Catat mesin unik untuk isi dropdown
            if (!in_array($macCode, $allMachines)) {
                $allMachines[] = $macCode;
            }

            // Jika ada filter mesin dan tidak cocok, lewati
            if ($selectedMac !== "" && $selectedMac !== $macCode) {
                continue;
            }

            // Mencegah data double berdasarkan kombinasi Mesin, Item, dan WO Number
            $woNumber = trim($row['wo_number']);
            $itemCode = trim($row['item_code']);
            
            // Buat kunci unik. Jika WO kosong, pakai uniqid agar tidak tertumpuk
            $uniqueKey = $macCode . '_' . $itemCode . '_' . ($woNumber !== "" ? $woNumber : uniqid());
            
            // Jika ada nomor WO dan kunci ini sudah pernah diproses, lewati (mencegah duplikat)
            if ($woNumber !== "" && isset($seenWoKeys[$uniqueKey])) {
                continue;
            }
            $seenWoKeys[$uniqueKey] = true;

            if (!isset($groupedData[$macCode])) {
                $groupedData[$macCode] = array();
            }
            $groupedData[$macCode][] = $row;
        }
    } while (sqlsrv_next_result($stmt));
    
    sqlsrv_free_stmt($stmt);
}

// Urutkan daftar dropdown mesin dan group mesin
sort($allMachines);
ksort($groupedData);

// Mengurutkan data di dalam masing-masing mesin berdasarkan tanggal WO START (Ascending) 
// Kompatibel dengan PHP 5.4
foreach ($groupedData as $macCode => &$rows) {
    usort($rows, function($a, $b) {
        $dateA = $a['wo_start'];
        $dateB = $b['wo_start'];
        
        if ($dateA instanceof DateTime && $dateB instanceof DateTime) {
            if ($dateA == $dateB) return 0;
            return ($dateA < $dateB) ? -1 : 1;
        }
        
        $timeA = strtotime(is_string($dateA) ? $dateA : '');
        $timeB = strtotime(is_string($dateB) ? $dateB : '');
        
        if ($timeA == $timeB) return 0;
        return ($timeA < $timeB) ? -1 : 1;
    });
}
unset($rows); 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>MC RUN REPORT</title>
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 11px;
            background: #fff;
            color: #000;
            padding: 20px;
        }
        
        .report-header { margin-bottom: 20px; }
        .report-header h2 {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 5px 0;
            letter-spacing: 0.5px;
        }
        .report-header h3 { font-size: 14px; font-weight: bold; margin: 0; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: auto; }
        .report-table th, .report-table td {
            padding: 4px 5px;
            vertical-align: middle;
            text-align: left;
            border: 1px solid #000;
        }
        .report-table thead th {
            font-weight: bold;
            font-size: 10px;
            text-align: center;
            background-color: #f9f9f9;
        }
        .report-table .center { text-align: center; }
        .report-table .num { text-align: right; }
        .blue-text { color: #0000FF; }
        .group-header td {
            font-weight: bold;
            padding-top: 10px;
            padding-bottom: 10px;
            font-size: 12px;
            background-color: #efefef;
        }
        .qr-cell { padding: 6px 4px !important; text-align: center; }
        .qr-cell img { display: block; margin: 0 auto; background: #fff; }
        .no-print {
            background: #f5f5f5; padding: 10px; border: 1px solid #ddd;
            margin-bottom: 20px; font-family: Arial, sans-serif;
        }
        .no-print input[type="date"], .no-print select, .no-print button {
            padding: 5px; margin-right: 10px;
        }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <form method="GET">
        <b>Report MC Run</b> &nbsp; | &nbsp;
        Dari: <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
        Ke: <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
        
        Mesin: 
        <select name="mac_code">
            <option value="">-- Semua Mesin --</option>
            <?php foreach ($allMachines as $mc): ?>
                <option value="<?php echo htmlspecialchars($mc); ?>" <?php echo ($selectedMac === $mc) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($mc); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Tampilkan</button>
        <button type="button" onclick="window.print()">Print Report</button>
        <a href="dashboard_home.php" style="float: right; padding: 5px; text-decoration: none; background: #ddd; color:#333; border: 1px solid #ccc;">Kembali ke Dashboard</a>
    </form>
</div>

<?php if ($errorMsg !== ""): ?>
    <div style="color: red; border: 1px solid red; padding: 10px; margin-bottom: 15px;">
        <strong>Error Database:</strong><br><?php echo $errorMsg; ?>
    </div>
<?php endif; ?>

<div class="report-header">
    <h2>PT.IMC TEKNO INDONESIA</h2>
    <h3>MC RUN : <?php echo date('F Y', strtotime($fromDate)); ?></h3>
</div>

<table class="report-table">
    <thead>
        <tr>
            <th rowspan="2">ITEM CODE</th>
            <th rowspan="2">ITEM NAME</th>
            <th rowspan="2">CUSTOMER</th>
            <th rowspan="2">STD PACK</th>
            <th rowspan="2">STD PACK BOX</th>
            <th rowspan="2">MATERIAL LABEL</th>
            <th rowspan="2">MAKER</th>
            <th rowspan="2">WARNA LABEL</th>
            <th rowspan="2" class="center">QR CODE</th>
            <th colspan="3" class="center">DAYS</th>
            <th colspan="2" class="center">WO</th>
            <th colspan="2" class="center">ACTUAL PROD</th>
            <th rowspan="2">WO NUMBER</th>
            <th rowspan="2" class="num">PLAN</th>
            <th rowspan="2" class="num">ACT</th>
        </tr>
        <tr>
            <th class="center">PLAN</th>
            <th class="center">ACT</th>
            <th class="center">BAL</th>
            <th class="center">START</th>
            <th class="center">END</th>
            <th class="center">START</th>
            <th class="center">END</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($groupedData as $macCode => $rows): ?>
            
            <tr class="group-header">
                <td colspan="19"><?php echo h($macCode); ?></td>
            </tr>

            <?php foreach ($rows as $r): 
                $daysPlan = calculateDays($r['wo_start'], $r['wo_end']);
                $daysAct  = calculateDays($r['pd_date_awal'], $r['pd_date_akhir']);
                
                $bal = $daysAct - $daysPlan;
                $displayBal = ($bal > 0) ? $bal : '-';
                
                $planQty = number_format((float)(isset($r['wo_qty']) ? $r['wo_qty'] : 0), 0, ',', ',');
                $actQty  = (isset($r['pd_qty']) && $r['pd_qty'] > 0) ? number_format((float)$r['pd_qty'], 0, ',', ',') : '0';

                $woNumber = trim((string)$r['wo_number']);
                $itemCodeTrimmed = trim((string)$r['item_code']);

                $stdPackValue = isset($stdPackData[$itemCodeTrimmed]['STD_PACK']) ? $stdPackData[$itemCodeTrimmed]['STD_PACK'] : '';
                $stdPackBoxValue = isset($stdPackData[$itemCodeTrimmed]['STD_PACK_BOX']) ? $stdPackData[$itemCodeTrimmed]['STD_PACK_BOX'] : '';
                
                // MENGAMBIL MAT_NAME LANGSUNG DARI STORED PROCEDURE (Bukan dari STD_PACK lagi)
                // Cek key MAT_NAME besar atau kecil (tergantung driver sqlsrv return arraynya)
                if (isset($r['MAT_NAME'])) {
                    $matNameValue = trim((string)$r['MAT_NAME']);
                } elseif (isset($r['mat_name'])) {
                    $matNameValue = trim((string)$r['mat_name']);
                } else {
                    $matNameValue = '';
                }
                
                $makerValue = isset($stdPackData[$itemCodeTrimmed]['MAKER']) ? $stdPackData[$itemCodeTrimmed]['MAKER'] : '';
                $warnaLabelValue = isset($stdPackData[$itemCodeTrimmed]['WARNA_LABEL']) ? $stdPackData[$itemCodeTrimmed]['WARNA_LABEL'] : '';
            ?>
            <tr>
                <td><?php echo h($r['item_code']); ?></td>
                <td><?php echo h($r['item_name']); ?></td>
                <td><?php echo h($r['cust_comp']); ?></td>
                
                <td class="center"><?php echo h($stdPackValue); ?></td>
                <td class="center"><?php echo h($stdPackBoxValue); ?></td>
                <td><?php echo h($matNameValue); ?></td>
                <td><?php echo h($makerValue); ?></td>
                <td class="center"><?php echo h($warnaLabelValue); ?></td>

                <td class="qr-cell">
                    <?php if (!empty($woNumber)): ?>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&margin=2&data=<?php echo urlencode($woNumber); ?>" 
                             alt="QR" 
                             width="40" 
                             height="40">
                    <?php endif; ?>
                </td>
                
                <td class="center"><?php echo $daysPlan > 0 ? $daysPlan : ''; ?></td>
                <td class="center"><?php echo $daysAct > 0 ? $daysAct : ''; ?></td>
                <td class="center"><?php echo $displayBal; ?></td>
                
                <td class="center"><?php echo h($r['wo_start']); ?></td>
                <td class="center"><?php echo h($r['wo_end']); ?></td>
                
                <td class="center"><?php echo h($r['pd_date_awal']); ?></td>
                <td class="center"><?php echo h($r['pd_date_akhir']); ?></td>
                
                <td><?php echo h($r['wo_number']); ?></td>
                <td class="num blue-text"><?php echo $planQty; ?></td>
                <td class="num"><?php echo $actQty; ?></td>
            </tr>
            <?php endforeach; ?>

        <?php endforeach; ?>
        
        <?php if(empty($groupedData)): ?>
            <tr>
                <td colspan="19" class="center" style="padding: 20px;">No data available</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>