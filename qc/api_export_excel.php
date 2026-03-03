<?php
// FILE: msii/qc/api_export_excel.php
if (ob_get_length()) ob_end_clean();

if (session_status() == PHP_SESSION_NONE) { session_start(); }
$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';
$plant_label = ($active_plant == 'p2') ? "PLANT_2" : "PLANT_1";

header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Data_Claim_Kakotora_".$plant_label."_".date('Ymd').".xls");
header("Pragma: no-cache");
header("Expires: 0");

error_reporting(0);
ini_set('display_errors', 0);
define('LOGIN_PAGE', true);

// PILIH KONEKSI
if ($active_plant == 'p2') {
    if(!isset($_SESSION['erp_user'])) $_SESSION['erp_user'] = $_SESSION['db_user'];
    if(!isset($_SESSION['erp_pass'])) $_SESSION['erp_pass'] = $_SESSION['db_pass'];
    $_SESSION['server_sql'] = "192.168.0.9"; // !!! ISI IP PLANT 2 DI SINI !!!
    require_once __DIR__ . '/../config/database.php'; 
} else {
    require_once __DIR__ . '/../config/database_p1.php'; 
}
?>
<!-- ... (KODE TABEL HTML DI BAWAHNYA SAMA PERSIS SEPERTI YANG SEBELUMNYA) ... -->
<table border="1">
    <thead>
        <tr style="background-color: #4CAF50; color:white;">
            <th>No</th>
            <th>No. CAR</th>
            <th>Tanggal Claim</th>
            <th>Customer</th>
            <th>Part Name</th>
            <th>Problem</th>
            <th>Qty</th>
            <th>Status</th>
            <th>Klasifikasi</th>
            <th>Lokasi</th>
            <th>Penyebab</th>
            <th>Solusi</th>
            <th>PIC</th>
            <th>Tgl Efektif</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($conn) {
            $sql = "SELECT a.car_no, a.claim_date, a.problem, a.qty, a.event_status, c.CUST_COMP, v.PART_NAME, b.cause, b.counter, b.pic, b.eff_date, (SELECT TOP 1 klasifikasi FROM car_klasifikasi WHERE car_id = a.car_id) as klasifikasi, (SELECT TOP 1 loc_problem FROM car_loc WHERE car_id = a.car_id) as lokasi FROM car_claim a LEFT JOIN CUST c ON a.cust_id = c.CUST_ID LEFT JOIN ITEM_CUSTINFO_VIEW v ON a.item_id = v.ITEM_ID LEFT JOIN car_claim_detail b ON a.car_id = b.car_id ORDER BY a.claim_date DESC";
            $stmt = sqlsrv_query($conn, $sql);
            $no = 1;
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $tgl = ($row['claim_date']) ? $row['claim_date']->format('d-m-Y') : '-';
                    $eff = ($row['eff_date']) ? $row['eff_date']->format('d-m-Y') : '-';
                    $bg = ($no % 2 == 0) ? '#f9f9f9' : '#ffffff';
                    echo "<tr style='background-color: $bg;'>";
                    echo "<td>" . $no++ . "</td>";
                    echo "<td>" . $row['car_no'] . "</td>";
                    echo "<td>" . $tgl . "</td>";
                    echo "<td>" . (isset($row['CUST_COMP'])?$row['CUST_COMP']:'-') . "</td>";
                    echo "<td>" . (isset($row['PART_NAME'])?$row['PART_NAME']:'-') . "</td>";
                    echo "<td>" . $row['problem'] . "</td>";
                    echo "<td>" . $row['qty'] . "</td>";
                    echo "<td>" . $row['event_status'] . "</td>";
                    echo "<td>" . (isset($row['klasifikasi']) ? $row['klasifikasi'] : '-') . "</td>";
                    echo "<td>" . (isset($row['lokasi']) ? $row['lokasi'] : '-') . "</td>";
                    echo "<td>" . (isset($row['cause'])?$row['cause']:'-') . "</td>";
                    echo "<td>" . (isset($row['counter'])?$row['counter']:'-') . "</td>";
                    echo "<td>" . (isset($row['pic'])?$row['pic']:'-') . "</td>";
                    echo "<td>" . $eff . "</td>";
                    echo "</tr>";
                }
            }
        }
        ?>
    </tbody>
</table>