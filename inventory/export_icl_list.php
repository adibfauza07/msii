<?php
require_once __DIR__ . '/../config/database_p1.php';

// 1. Tangkap Parameter Tanggal
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate   = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// 2. Logika Query dan Filter (Sama seperti print_icl_list.php)
$conditions = ["T.TRTY_CODE IN ('01', '12')"];
$params = array();
$periodeText = "Semua Waktu (All Time)";

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

$whereClause = "WHERE " . implode(" AND ", $conditions);

$sql = "SELECT T.TRAN_DOC, T.TRAN_DATE, S.SUP_COMP 
        FROM TRANS T 
        LEFT JOIN SUPPLIER S ON T.SUP_CODE = S.SUP_CODE
        $whereClause
        ORDER BY T.TRAN_DATE ASC, T.TRAN_DOC ASC";

$stmt = sqlsrv_query($conn, $sql, $params);

// 3. Set Header Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Rekap_ICL_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
?>

<table border="1">
    <thead>
        <tr>
            <th colspan="4" style="font-size: 14px; font-weight: bold; text-align: center; background-color: #cccccc;">REKAP LIST INCOMING CHECK LIST (ICL)</th>
        </tr>
        <tr>
            <th colspan="4" style="text-align: center; background-color: #cccccc;">Periode: <?php echo $periodeText; ?></th>
        </tr>
        <tr style="background-color: #f2f2f2;">
            <th style="width: 50px;">No</th>
            <th style="width: 100px;">Tanggal</th>
            <th style="width: 200px;">Nomor ICL</th>
            <th style="width: 350px;">Nama Supplier</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        if ($stmt === false) {
            echo "<tr><td colspan='4'>Error Database</td></tr>";
        } else {
            $no = 1;
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $tgl = ($row['TRAN_DATE'] instanceof DateTime) ? $row['TRAN_DATE']->format('d-m-Y') : $row['TRAN_DATE'];
                $sup = !empty($row['SUP_COMP']) ? htmlspecialchars($row['SUP_COMP']) : '-';
                
                echo "<tr>
                        <td style='text-align: center;'>{$no}</td>
                        <td style='text-align: center;'>{$tgl}</td>
                        <td style='font-weight: bold;'>{$row['TRAN_DOC']}</td>
                        <td>{$sup}</td>
                      </tr>";
                $no++;
            }
        }
        ?>
    </tbody>
</table>