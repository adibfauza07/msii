<?php
require_once __DIR__ . '/config/database.php';

// Ambil parameter tanggal dari form (bisa kosong)
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate   = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Array untuk menampung kondisi WHERE dan Parameter
// KONDISI WAJIB: Hanya tampilkan Tipe Transaksi 01 dan 12
$conditions = ["T.TRTY_CODE IN ('01', '12')"];
$params = array();

$periodeText = "Semua Waktu (All Time)";

// Logika Filter Tanggal Bebas
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

// Gabungkan semua kondisi dengan AND
$whereClause = "WHERE " . implode(" AND ", $conditions);

// Query Dinamis - DIURUTKAN BERDASARKAN TANGGAL & NOMOR
$sql = "SELECT T.TRAN_DOC, T.TRAN_DATE, S.SUP_COMP 
        FROM TRANS T 
        LEFT JOIN SUPPLIER S ON T.SUP_CODE = S.SUP_CODE
        $whereClause
        ORDER BY T.TRAN_DATE ASC, T.TRAN_DOC ASC";

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("<div style='color:red;'><b>Error Database:</b><br>" . print_r(sqlsrv_errors(), true) . "</div>");
}

$dataList = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dataList[] = $row;
}

$companyName = isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2' ? "PT. IMC TEKNO INDONESIA PLANT 2" : "PT. IMC TEKNO INDONESIA PLANT 1";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap ICL</title>
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
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn" onclick="window.print()">Print Report</button>
        <a href="export_icl_list.php?start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>" class="btn" style="text-decoration:none; color:black;">Export to Excel</a>
        <button class="btn" onclick="window.close()">Tutup</button>
    </div>

    <div class="container">
        <div class="header">
            <h2><?php echo $companyName; ?></h2>
            <h3>REKAP LIST INCOMING CHECK LIST (ICL)</h3>
            <p>Periode: <b><?php echo $periodeText; ?></b></p>
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
                    echo "<tr><td colspan='4' class='text-center'>Tidak ada data ICL pada periode ini.</td></tr>";
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

</body>
</html>