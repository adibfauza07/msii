<?php
// FILE INI BERTUGAS MEMBUAT EXCEL DARI DATA LIST

// ==========================================
// 1. KONEKSI MANDIRI (DINAMIS PLANT 1 / PLANT 2)
// ==========================================
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';

if ($active_plant == 'p2') {
    if(!isset($_SESSION['erp_user'])) $_SESSION['erp_user'] = $_SESSION['db_user'];
    if(!isset($_SESSION['erp_pass'])) $_SESSION['erp_pass'] = $_SESSION['db_pass'];
    $_SESSION['server_sql'] = "192.168.0.9"; 
    require_once __DIR__ . '/../config/database.php';
} else {
    require_once __DIR__ . '/../config/database_p1.php';
}

$plant_label = strtoupper($active_plant);
$header_bg   = ($active_plant == 'p2') ? '#ff5f5f' : '#0d6efd'; // Merah (P2), Biru (P1)

// Atur header untuk memberitahu browser bahwa ini adalah file Excel
$filename = "Daftar_Usulan_Perubahan_" . $plant_label . "_" . date('Ymd') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

// === Ambil Parameter filter ===
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$cust_id    = isset($_GET['cust_id']) ? $_GET['cust_id'] : '';
$kode_usul  = isset($_GET['kode_usul']) ? $_GET['kode_usul'] : '';
$request_by = isset($_GET['request_by']) ? $_GET['request_by'] : '';
$remark     = isset($_GET['remark']) ? $_GET['remark'] : '';

// === Query utama ===
$sql = "
SELECT 
    U.USUL_ID, U.KODE_USUL, U.ISSUE_DATE, U.JUDUL_DOK, U.DESCRIPTION, U.PJ, 
    U.ISI_REVISI, U.ALASAN_REVISI, U.PLANT_DATE, U.TARGET_DATE, U.REMARKS, U.ITEM_NO,
    C.CUST_COMP,
    I.ITEM_CODE, I.ITEM_NAME,
    (CASE WHEN U.BARU = 1 THEN 'Baru' ELSE '' END) as TIPE_BARU,
    (CASE WHEN U.REVISI = 1 THEN 'Revisi' ELSE '' END) as TIPE_REVISI,
    U.REVISI_1,
    (CASE WHEN U.IS_STD = 1 THEN 'Y' ELSE '' END) as DOK_IS_STD,
    (CASE WHEN U.FMEA = 1 THEN 'Y' ELSE '' END) as DOK_FMEA,
    (CASE WHEN U.WI = 1 THEN 'Y' ELSE '' END) as DOK_WI,
    (CASE WHEN U.QCPC = 1 THEN 'Y' ELSE '' END) as DOK_QCPC,
    (CASE WHEN U.CHECK_POINT = 1 THEN 'Y' ELSE '' END) as DOK_CHECK_POINT,
    (CASE WHEN U.SETTING_PAR = 1 THEN 'Y' ELSE '' END) as DOK_SETTING_PAR,
    (CASE WHEN U.STD_PACK = 1 THEN 'Y' ELSE '' END) as DOK_STD_PACK,
    (CASE WHEN U.OTHER = 1 THEN 'Y' ELSE '' END) as DOK_OTHER
FROM 
    USULAN_PERUBAHAN U
LEFT JOIN 
    CUST C ON C.CUST_ID = U.CUST_ID
LEFT JOIN 
    ITEMS I ON I.ITEM_ID = U.ITEM_ID
WHERE 
    U.ISSUE_DATE BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if (!empty($cust_id)) { $sql .= " AND U.CUST_ID = ?"; $params[] = $cust_id; }
if (!empty($kode_usul)) { $sql .= " AND U.KODE_USUL LIKE ?"; $params[] = "%".$kode_usul."%"; }
if (!empty($request_by)) { $sql .= " AND U.PJ = ?"; $params[] = $request_by; }
if (!empty($remark)) { $sql .= " AND U.REMARKS = ?"; $params[] = $remark; }

$sql .= " ORDER BY U.ISSUE_DATE DESC, U.KODE_USUL DESC";

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo "Error Query: " . print_r(sqlsrv_errors(), true);
    exit;
}

// Mulai membuat output tabel HTML (yang akan jadi Excel)
echo "<table border='1' width='100%'>";
echo "<thead>";
echo "<tr style='background-color:{$header_bg}; color:white;'>";
echo "<th>No</th><th>Kode Usulan</th><th>Issue Date</th><th>Customer</th><th>Part No</th><th>Part Name</th>";
echo "<th>Document Name</th><th>Request By</th><th>Tipe Usulan</th><th>No. Revisi</th><th>Isi Revisi</th>";
echo "<th>Alasan Revisi</th><th>Plan Date</th><th>Target Date</th><th>Remark</th><th>Inspection STD</th>";
echo "<th>FMEA</th><th>Work Instruction</th><th>QCPC</th><th>Check Point</th><th>Setting Parameter</th>";
echo "<th>Packing STD</th><th>Other</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

$no = 1;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $issue_date  = $row['ISSUE_DATE'] ? $row['ISSUE_DATE']->format('Y-m-d') : '-';
    $plant_date  = $row['PLANT_DATE'] ? $row['PLANT_DATE']->format('Y-m-d') : '-';
    $target_date = $row['TARGET_DATE'] ? $row['TARGET_DATE']->format('Y-m-d') : '-';
    
    $tipe_usulan = $row['TIPE_BARU'];
    if(!empty($row['TIPE_REVISI'])) {
        $tipe_usulan = (!empty($tipe_usulan)) ? $tipe_usulan . ', ' . $row['TIPE_REVISI'] : $row['TIPE_REVISI'];
    }
    
    echo "<tr>";
    echo "<td>" . $no++ . "</td>";
    echo "<td>" . htmlspecialchars($row['KODE_USUL']) . "</td>";
    echo "<td>" . $issue_date . "</td>";
    echo "<td>" . htmlspecialchars($row['CUST_COMP']) . "</td>";
    echo "<td>" . htmlspecialchars($row['ITEM_NO']) . "</td>";
    echo "<td>" . htmlspecialchars($row['ITEM_NAME']) . "</td>";
    echo "<td>" . htmlspecialchars($row['JUDUL_DOK']) . "</td>";
    echo "<td>" . htmlspecialchars($row['PJ']) . "</td>";
    echo "<td>" . htmlspecialchars($tipe_usulan) . "</td>";
    echo "<td>" . htmlspecialchars($row['REVISI_1']) . "</td>";
    echo "<td>" . htmlspecialchars($row['ISI_REVISI']) . "</td>";
    echo "<td>" . htmlspecialchars($row['ALASAN_REVISI']) . "</td>";
    echo "<td>" . $plant_date . "</td>";
    echo "<td>" . $target_date . "</td>";
    echo "<td>" . htmlspecialchars($row['REMARKS']) . "</td>";
    echo "<td>" . htmlspecialchars($row['DOK_IS_STD']) . "</td>";
    echo "<td>" . htmlspecialchars($row['DOK_FMEA']) . "</td>";
    echo "<td>" . htmlspecialchars($row['DOK_WI']) . "</td>";
    echo "<td>" . htmlspecialchars($row['DOK_QCPC']) . "</td>";
    echo "<td>" . htmlspecialchars($row['DOK_CHECK_POINT']) . "</td>";
    echo "<td>" . htmlspecialchars($row['DOK_SETTING_PAR']) . "</td>";
    echo "<td>" . htmlspecialchars($row['DOK_STD_PACK']) . "</td>";
    echo "<td>" . htmlspecialchars($row['DOK_OTHER']) . "</td>";
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";
exit;
?>
      