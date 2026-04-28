<?php
require_once __DIR__ . '/../config/database_p1.php';

// Set header agar output bisa diunduh sebagai file Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Trial_PE_Export_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// === Parameter filter ===
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$cust_id    = isset($_GET['cust_id']) ? $_GET['cust_id'] : '';

// === Query lengkap ===
$sql = "
SELECT 
    T.TRIAL_CODE, T.DATE, T.PART_CODE, IC.PART_NAME,
    C.CUST_COMP, T.QUANTITY_TRIAL, T.TRIAL_REASON, T.TRIAL_TIMES,
    T.MAT_USING, T.MAT_DRYING_TIME, T.MOLD_SET_UP, T.MOLD_SET_DOWN, T.TRIAL_DURATION,
    T.QE_COMMENT, T.PE_COMMENT, JD.JUDGE_TRIAL, T.PIC, T.WEIGHT_RUNNER,
    T.PREPARED, T.CHECKED, T.APPROVED, T.QTY_OK, T.QTY_NG, T.CYCLE_TIME_ACT,
    T.MAC_NO, J.JENIS_TRIAL, T.TONAGE, T.CORRECTIVE_ACTION, T.ANALYSYS
FROM TRIAL_PE T
LEFT JOIN ITEM_CUST_VIEW_TRIAL IC ON IC.PART_CODE = T.PART_CODE
LEFT JOIN CUST C ON C.CUST_ID = T.CUST_ID
LEFT JOIN TRIAL_PE_JENIS J ON J.ID = T.JENIS_ID
LEFT JOIN JUDGE_TRIAL JD ON JD.ID = T.JUDGE_ID
WHERE T.DATE BETWEEN ? AND ?";

$params = [$start_date, $end_date];
if (!empty($cust_id)) {
    $sql .= " AND T.CUST_ID = ?";
    $params[] = $cust_id;
}
$sql .= " ORDER BY T.DATE DESC";

$stmt = sqlsrv_query($conn, $sql, $params);

// === Output tabel Excel ===
echo "<table border='1' cellspacing='0' cellpadding='4'>";
echo "<tr style='background:#4a3ce5;color:white;text-align:center;font-weight:bold;'>
<th>No</th>
<th>Tanggal</th>
<th>Kode Trial</th>
<th>Part Code</th>
<th>Part Name</th>
<th>Customer</th>
<th>Qty Trial</th>
<th>Alasan Trial</th>
<th>Trial Ke</th>
<th>Material</th>
<th>Drying Time</th>
<th>Mold Set Up</th>
<th>Mold Set Down</th>
<th>Durasi Trial</th>
<th>QE Comment</th>
<th>PE Comment</th>
<th>Judge</th>
<th>PIC</th>
<th>Weight Runner</th>
<th>Prepared</th>
<th>Checked</th>
<th>Approved</th>
<th>Qty OK</th>
<th>Qty NG</th>
<th>Cycle Time</th>
<th>No Mesin</th>
<th>Jenis Trial</th>
<th>Tonnage</th>
<th>Corrective Action</th>
<th>Analisis</th>
</tr>";

if ($stmt && sqlsrv_has_rows($stmt)) {
    $no = 1;
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td align='center'>{$no}</td>";
        echo "<td align='center'>" . ($r['DATE'] ? date_format($r['DATE'], 'Y-m-d') : '') . "</td>";
        echo "<td>{$r['TRIAL_CODE']}</td>";
        echo "<td>{$r['PART_CODE']}</td>";
        echo "<td>{$r['PART_NAME']}</td>";
        echo "<td>{$r['CUST_COMP']}</td>";
        echo "<td align='center'>{$r['QUANTITY_TRIAL']}</td>";
        echo "<td>{$r['TRIAL_REASON']}</td>";
        echo "<td>{$r['TRIAL_TIMES']}</td>";
        echo "<td>{$r['MAT_USING']}</td>";
        echo "<td>{$r['MAT_DRYING_TIME']}</td>";
        echo "<td>{$r['MOLD_SET_UP']}</td>";
        echo "<td>{$r['MOLD_SET_DOWN']}</td>";
        echo "<td>{$r['TRIAL_DURATION']}</td>";
        echo "<td>{$r['QE_COMMENT']}</td>";
        echo "<td>{$r['PE_COMMENT']}</td>";
        echo "<td>{$r['JUDGE_TRIAL']}</td>";
        echo "<td>{$r['PIC']}</td>";
        echo "<td>{$r['WEIGHT_RUNNER']}</td>";
        echo "<td>{$r['PREPARED']}</td>";
        echo "<td>{$r['CHECKED']}</td>";
        echo "<td>{$r['APPROVED']}</td>";
        echo "<td>{$r['QTY_OK']}</td>";
        echo "<td>{$r['QTY_NG']}</td>";
        echo "<td>{$r['CYCLE_TIME_ACT']}</td>";
        echo "<td>{$r['MAC_NO']}</td>";
        echo "<td>{$r['JENIS_TRIAL']}</td>";
        echo "<td>{$r['TONAGE']}</td>";
        echo "<td>{$r['CORRECTIVE_ACTION']}</td>";
        echo "<td>{$r['ANALYSYS']}</td>";
        echo "</tr>";
        $no++;
    }
} else {
    echo "<tr><td colspan='30' align='center' style='color:red;'>Tidak ada data trial untuk periode ini.</td></tr>";
}

echo "</table>";
?>
