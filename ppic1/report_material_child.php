<?php
if (session_id() == "") {
    session_start();
}

// Menghubungkan ke database dengan cara yang sama seperti dashboard_home.php
require_once __DIR__ . "/../config/database_ppic.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

// Mengambil range tanggal input, default ke awal bulan s/d hari ini jika belum diisi
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$data = [];

// Data hanya akan ditarik jika user menekan tombol 'Tampilkan Laporan'
if (isset($_GET['search'])) {
    // Menambahkan jam agar cakupan pencarian tanggal SQL Server akurat semarian penuh
    $ts_from = $from_date . " 00:00:00";
    $ts_to = $to_date . " 23:59:59";

    // Format query untuk memanggil Stored Procedure SQL Server
    $sql = "{call sp_mat_use_plan_actual4(?, ?)}";
    $params = [
        [$ts_from, SQLSRV_PARAM_IN],
        [$ts_to, SQLSRV_PARAM_IN]
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("<pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Instruction Material Child Part Out To Production</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            font-size: 11px;
            background: #ffffff;
            margin: 20px;
            color: #000000;
        }
        .filter-container {
            background: #eeeeee;
            border: 1px solid #808080;
            padding: 12px;
            margin-bottom: 20px;
        }
        .filter-container input[type="date"], .filter-container button {
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            padding: 3px 6px;
        }
        .filter-container button {
            background: #d4d0c8;
            border: 2px outset #ffffff;
            cursor: pointer;
        }
        .filter-container button:active {
            border: 2px inset #ffffff;
        }
        .report-header {
            margin-bottom: 15px;
        }
        .report-title {
            font-size: 15px;
            font-weight: bold;
        }
        .report-subtitle {
            font-size: 12px;
            font-weight: bold;
            margin-top: 5px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .report-table th {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            padding: 6px;
            font-weight: bold;
        }
        .report-table td {
            padding: 5px 6px;
            border-bottom: 1px dashed #c0c0c0;
        }
        .report-table tr.total-row td {
            border-top: 1px solid #000000;
            border-bottom: 2px double #000000;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
    </style>
</head>
<body>

<div class="filter-container">
    <form method="GET" action="">
        <label>Periode Dari: </label>
        <input type="date" name="from_date" value="<?php echo h($from_date); ?>" required>
        &nbsp;&nbsp;
        <label>Sampai: </label>
        <input type="date" name="to_date" value="<?php echo h($to_date); ?>" required>
        &nbsp;&nbsp;
        <button type="submit" name="search" value="1">Tampilkan Laporan</button>
    </form>
</div>

<?php if (isset($_GET['search'])): ?>
    <div class="report-header">
        <div class="report-title">INSTUCTION MATERIAL CHILD PART OUT TO PRODUCTION PERIODE :</div>
        <div class="report-subtitle">
            PLAN VS ACTUAL &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            FROM : <?php echo h(date('d-M-Y', strtotime($from_date))); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            TO : <?php echo h(date('d-M-Y', strtotime($to_date))); ?>
        </div>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th rowspan="2" class="text-left" style="width: 90px;">CODE</th>
                <th rowspan="2" class="text-left">NAME</th>
                <th rowspan="2" class="text-right" style="width: 100px;">PLAN PROD</th>
                <th rowspan="2" class="text-right" style="width: 100px;">MAT OUT PROD</th>
                <th rowspan="2" class="text-right" style="width: 100px;">ACT PROD</th>
                <th colspan="2" class="text-center" style="border-bottom: 1px solid #000000;">BALANCE PROD</th>
            </tr>
            <tr>
                <th class="text-right" style="width: 100px; border-bottom: 1px solid #000000;">BAL PLAN</th>
                <th class="text-right" style="width: 100px; border-bottom: 1px solid #000000;">BAL ACT</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Variabel penampung total akhir (Grand Total)
            $grand_plan = 0;
            $grand_out = 0;
            $grand_act = 0;
            $grand_bal_plan = 0;
            $grand_bal_act = 0;

            if (count($data) > 0):
                foreach ($data as $row):
                    $plan = (float)$row['PS_QTY'];
                    $out  = (float)$row['OUT_PROD'];
                    $act  = (float)$row['PD_ACT'];

                    // Rumus Balance mengikuti logic template laporan asli Anda
                    $bal_plan = $out - $plan;
                    $bal_act  = $out - $act;

                    // Tambahkan ke grand total
                    $grand_plan += $plan;
                    $grand_out  += $out;
                    $grand_act  += $act;
                    $grand_bal_plan += $bal_plan;
                    $grand_bal_act  += $bal_act;
            ?>
                <tr>
                    <td class="text-left"><?php echo h($row['ITEM_CODE']); ?></td>
                    <td class="text-left"><?php echo h($row['ITEM_NAME']); ?></td>
                    
                    <td class="text-right"><?php echo $plan != 0 ? number_format($plan, 0, '.', ',') : '-'; ?></td>
                    <td class="text-right"><?php echo $out != 0 ? number_format($out, 0, '.', ',') : '-'; ?></td>
                    <td class="text-right"><?php echo $act != 0 ? number_format($act, 0, '.', ',') : '-'; ?></td>
                    <td class="text-right"><?php echo $bal_plan != 0 ? number_format($bal_plan, 0, '.', ',') : '-'; ?></td>
                    <td class="text-right"><?php echo $bal_act != 0 ? number_format($bal_act, 0, '.', ',') : '-'; ?></td>
                </tr>
            <?php 
                endforeach; 
            ?>
                <tr class="total-row">
                    <td colspan="2" class="text-left">TOTAL:</td>
                    <td class="text-right"><?php echo number_format($grand_plan, 2, '.', ','); ?></td>
                    <td class="text-right"><?php echo number_format($grand_out, 2, '.', ','); ?></td>
                    <td class="text-right"><?php echo number_format($grand_act, 2, '.', ','); ?></td>
                    <td class="text-right"><?php echo number_format($grand_bal_plan, 2, '.', ','); ?></td>
                    <td class="text-right"><?php echo number_format($grand_bal_act, 2, '.', ','); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center" style="padding: 25px;">Tidak ditemukan data transaksi untuk periode tanggal tersebut.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>