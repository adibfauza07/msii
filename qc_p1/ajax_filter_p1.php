<?php
// Koneksi ke Database PLANT 1
require_once '../config/Database_p1.php'; 

// === Parameter filter ===
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end_date   = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
$cust_id    = isset($_POST['cust_id']) ? $_POST['cust_id'] : '';
$kode_usul  = isset($_POST['kode_usul']) ? $_POST['kode_usul'] : '';
$request_by = isset($_POST['request_by']) ? $_POST['request_by'] : '';
$remark     = isset($_POST['remark']) ? $_POST['remark'] : '';

$has_filter = true; 

// === Query utama ===
$sql = "
SELECT 
    U.USUL_ID, U.KODE_USUL, U.ISSUE_DATE, U.JUDUL_DOK, U.DESCRIPTION, U.PJ, 
    U.ISI_REVISI, U.ALASAN_REVISI, U.PLANT_DATE, U.TARGET_DATE, U.REMARKS,
    C.CUST_COMP,
    I.ITEM_CODE, I.ITEM_NAME,
    I.ITEM_NO
FROM 
    USULAN_PERUBAHAN U
LEFT JOIN 
    CUST C ON C.CUST_ID = U.CUST_ID
LEFT JOIN 
    ITEMS I ON I.ITEM_ID = U.ITEM_ID
";
$params = [];
$data_rows = [];

// Terapkan filter
$sql .= " WHERE U.ISSUE_DATE BETWEEN ? AND ?";
$params[] = $start_date;
$params[] = $end_date;

if (!empty($cust_id)) {
    $sql .= " AND U.CUST_ID = ?";
    $params[] = $cust_id;
}
if (!empty($kode_usul)) {
    $sql .= " AND U.KODE_USUL LIKE ?";
    $params[] = "%".$kode_usul."%";
}
if (!empty($request_by)) {
    $sql .= " AND U.PJ = ?";
    $params[] = $request_by;
}
if (!empty($remark)) {
    $sql .= " AND U.REMARKS = ?";
    $params[] = $remark;
}

$sql .= " ORDER BY U.ISSUE_DATE DESC, U.KODE_USUL DESC";

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode([
        'buttons' => '<div class="alert alert-danger">Error Query.</div>',
        'table' => '<tr><td colspan="13" class="text-center text-danger">Error: ' . print_r(sqlsrv_errors(), true) . '</td></tr>'
    ]);
    die();
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data_rows[] = $row;
}

// RENDER HTML
$query_string = http_build_query($_POST);

ob_start(); 
?>
<div class="card card-export">
    <div class="card-body d-flex justify-content-end align-items-center">
        <div class="d-flex gap-2">
            <!-- (FIX) Path ../qc_p1/ agar bisa diakses dari dashboard_qc.php -->
            <a href="<?php echo '../qc_p1/export_usulan.php?' . $query_string; ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </a>
            <button class="btn btn-info" onclick="printFormByKode_P1()">
                <i class="bi bi-printer"></i> Print Form by Kode
            </button>
            <a href="<?php echo '../qc_p1/report_list_all_print.php?' . $query_string; ?>" target="_blank" class="btn btn-danger">
                <i class="bi bi-file-earmark-pdf"></i> Print List All
            </a>
            <a href="../qc_p1/report_list_open_print.php?..." target="_blank" class="btn btn-danger">
    <i class="bi bi-printer"></i> Print List Open
</a>
        </div>
    </div>
</div>
<?php
$html_buttons = ob_get_clean(); 

ob_start(); 
?>
<?php if (empty($data_rows)): ?>
    <tr>
        <td colspan="13" class="text-center text-danger">Tidak ada data yang ditemukan untuk filter ini.</td>
    </tr>
<?php else: ?>
    <?php foreach ($data_rows as $row): ?>
    <tr>
        <td class="text-nowrap text-center">
            <a href="../qc_p1/edit_usulan.php?kode_usul=<?= $row['KODE_USUL'] ?>" class="btn btn-warning btn-sm" title="Edit">
                <i class="bi bi-pencil"></i>
            </a>
            <button class="btn btn-danger btn-sm btn-delete" 
                    data-bs-toggle="modal" 
                    data-bs-target="#confirmDeleteModal" 
                    data-id="<?= $row['KODE_USUL'] ?>"
                    data-info="Usulan: <?= htmlspecialchars($row['KODE_USUL']) ?> (Customer: <?= htmlspecialchars($row['CUST_COMP']) ?>)"
                    title="Hapus">
                <i class="bi bi-trash"></i>
            </button>
        </td>
        <td class="text-nowrap"><?= htmlspecialchars($row['KODE_USUL']) ?></td>
        <td class="text-nowrap"><?= $row['ISSUE_DATE'] ? $row['ISSUE_DATE']->format('Y-m-d') : '-' ?></td>
        <td class="text-nowrap"><?= htmlspecialchars($row['CUST_COMP']) ?></td>
        <td class="text-nowrap"><?= htmlspecialchars($row['ITEM_NO']) ?></td>
        <td class="text-nowrap"><?= htmlspecialchars($row['ITEM_NAME']) ?></td>
        <td class="text-nowrap"><?= htmlspecialchars($row['JUDUL_DOK']) ?></td>
        <td class="text-nowrap"><?= htmlspecialchars($row['PJ']) ?></td>
        <td><?= htmlspecialchars($row['ISI_REVISI']) ?></td>
        <td><?= htmlspecialchars($row['ALASAN_REVISI']) ?></td>
        <td class="text-nowrap"><?= $row['PLANT_DATE'] ? $row['PLANT_DATE']->format('Y-m-d') : '-' ?></td>
        <td class="text-nowrap"><?= $row['TARGET_DATE'] ? $row['TARGET_DATE']->format('Y-m-d') : '-' ?></td>
        <td class="text-nowrap"><?= htmlspecialchars($row['REMARKS']) ?></td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
<?php
$html_table = ob_get_clean(); 

echo json_encode([
    'buttons' => $html_buttons,
    'table' => $html_table
]);

die();
?>