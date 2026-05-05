<?php
// msii/pe/export_excel_pe.php
require_once '../config/database_p1.php';

// 1. Tangkap Parameter Filter dari URL (Dari Dashboard)
$start = !empty($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end   = !empty($_GET['end']) ? $_GET['end'] : date('Y-m-t'); 
$filter_part = isset($_GET['filter_part']) ? trim($_GET['filter_part']) : '';
$filter_cust = isset($_GET['filter_cust']) ? trim($_GET['filter_cust']) : '';

// 2. Deklarasikan Header agar Browser Mengunduh sebagai File Excel (.xls)
$filename = "Rekap_Trial_PE_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 3. Susun Query Database Dinamis
$sql = "SELECT T.*, I.ITEM_NAME, C.CUST_COMP, J.JUDGE_TRIAL 
        FROM TRIAL_PE T
        LEFT JOIN ITEMS I ON T.PART_CODE = I.ITEM_CODE
        LEFT JOIN CUST C ON T.CUST_ID = C.CUST_ID
        LEFT JOIN JUDGE_TRIAL J ON T.JUDGE_ID = J.ID
        WHERE 1=1 AND T.DATE BETWEEN ? AND ? ";
$params = array($start, $end);

if (!empty($filter_part)) {
    $sql .= " AND I.ITEM_NAME LIKE ? ";
    $params[] = "%" . $filter_part . "%"; 
}
if (!empty($filter_cust)) {
    $sql .= " AND C.CUST_COMP LIKE ? ";
    $params[] = "%" . $filter_cust . "%";
}
$sql .= " ORDER BY T.TRIAL_CODE DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
?>

<!-- 4. Cetak Tabel HTML (Excel akan otomatis membaca format tabel ini dengan rapi) -->
<table border="1" style="width: 100%; border-collapse: collapse;">
    <thead>
        <tr style="background-color: #1f2a36; color: #ffffff; font-weight: bold; text-align: center;">
            <th>NO. TRIAL</th>
            <th>TANGGAL</th>
            <th>KODE PART</th>
            <th>NAMA PART</th>
            <th>CUSTOMER</th>
            <th>MATERIAL</th>
            <th>OPERATION</th>
            <th>JUDGE</th>
            <th>PIC</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($stmt === false) {
            echo "<tr><td colspan='9'>Error Database</td></tr>";
        } else {
            $hasData = false;
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $hasData = true;
                
                // Format Tanggal
                $tgl = '-';
                if ($row['DATE'] instanceof DateTime) {
                    $tgl = $row['DATE']->format('Y-m-d');
                } elseif (!empty($row['DATE'])) {
                    $tgl = date('Y-m-d', strtotime($row['DATE']));
                }
                
                // Tampilkan Data Baris demi Baris
                echo "<tr>
                        <td style='text-align: center;'>#{$row['TRIAL_CODE']}</td>
                        <td style='text-align: center;'>{$tgl}</td>
                        <td>{$row['PART_CODE']}</td>
                        <td>{$row['ITEM_NAME']}</td>
                        <td>{$row['CUST_COMP']}</td>
                        <td>{$row['MAT_USING']}</td>
                        <td style='text-align: center;'>" . ($row['OPERATION'] ? $row['OPERATION'] : '-') . "</td>
                        <td style='text-align: center; font-weight: bold;'>" . ($row['JUDGE_TRIAL'] ? $row['JUDGE_TRIAL'] : 'BELUM JUDGE') . "</td>
                        <td style='text-align: center;'>{$row['PIC']}</td>
                      </tr>";
            }
            
            // Jika tidak ada data
            if (!$hasData) {
                echo "<tr><td colspan='9' style='text-align:center;'>Belum ada data Trial sesuai filter pencarian Anda.</td></tr>";
            }
        }
        ?>
    </tbody>
</table>