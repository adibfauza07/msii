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

$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : $defaultFrom;
$toDate   = isset($_GET['to_date']) ? $_GET['to_date'] : $defaultTo;

$startSql = $fromDate . ' 00:00:00.000';
$endSql   = $toDate . ' 23:59:59.997';

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

// Mengambil data dan melakukan Grouping berdasarkan MAC_CODE
$groupedData = array();
if ($stmt !== false) {
    do {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $macCode = trim($row['mac_code']);
            if ($macCode === "") $macCode = "Tanpa Mesin";
            
            if (!isset($groupedData[$macCode])) {
                $groupedData[$macCode] = array();
            }
            $groupedData[$macCode][] = $row;
        }
    } while (sqlsrv_next_result($stmt));
    
    sqlsrv_free_stmt($stmt);
}

// Urutkan group berdasarkan nomor mesin
ksort($groupedData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>MC RUN REPORT</title>
    <style>
        /* Desain khusus untuk meniru format print Crystal Reports */
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 11px;
            background: #fff;
            color: #000;
            padding: 20px;
        }
        
        .report-header {
            margin-bottom: 20px;
        }
        
        .report-header h2 {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 5px 0;
            letter-spacing: 0.5px;
        }
        
        .report-header h3 {
            font-size: 14px;
            font-weight: bold;
            margin: 0;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        .report-table th, .report-table td {
            padding: 4px 5px;
            vertical-align: top;
            text-align: left;
        }

        /* Garis tabel hanya ada di atas dan bawah header seperti Crystal Reports */
        .report-table thead th {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            font-weight: bold;
            font-size: 10px;
        }

        /* Hilangkan border bawah pada header baris pertama agar menyatu dengan baris kedua */
        .report-table thead tr:first-child th[colspan] {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .report-table thead tr:last-child th {
            border-top: none;
            padding-top: 2px;
        }

        .report-table .center { text-align: center; }
        .report-table .num { text-align: right; }
        
        .blue-text {
            color: #0000FF;
        }

        .group-header td {
            font-weight: bold;
            padding-top: 15px;
            padding-bottom: 5px;
            font-size: 12px;
        }

        /* Form Filter (Tidak akan tercetak) */
        .no-print {
            background: #f5f5f5;
            padding: 10px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
            font-family: Arial, sans-serif;
        }
        .no-print input[type="date"], .no-print button {
            padding: 5px;
            margin-right: 10px;
        }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

<!-- Form Filter (disembunyikan saat print) -->
<div class="no-print">
    <form method="GET">
        <b>Report MC Run</b> &nbsp; | &nbsp;
        Dari: <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
        Ke: <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
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
            
            <!-- Baris Nama Mesin (Grouping) -->
            <tr class="group-header">
                <td colspan="13"><?php echo h($macCode); ?></td>
            </tr>

            <!-- Daftar Item pada Mesin Tersebut -->
            <?php foreach ($rows as $r): 
                // Kalkulasi DAYS
                $daysPlan = calculateDays($r['wo_start'], $r['wo_end']);
                $daysAct  = calculateDays($r['pd_date_awal'], $r['pd_date_akhir']);
                
                $bal = $daysAct - $daysPlan;
                $displayBal = ($bal > 0) ? $bal : '-'; // Menampilkan '-' jika tidak ada balance/keterlambatan
                
                // Jika QTY bernilai 0, kita kosongkan saja / sesuaikan
                $planQty = number_format((float)(isset($r['wo_qty']) ? $r['wo_qty'] : 0), 0, ',', ',');
                $actQty  = (isset($r['pd_qty']) && $r['pd_qty'] > 0) ? number_format((float)$r['pd_qty'], 0, ',', ',') : '0';
            ?>
            <tr>
                <td><?php echo h($r['item_code']); ?></td>
                <td><?php echo h($r['item_name']); ?></td>
                <td><?php echo h($r['cust_comp']); ?></td>
                
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
                <td colspan="13" class="center" style="padding: 20px;">No data available</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>