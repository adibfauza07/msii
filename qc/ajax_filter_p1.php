<?php
// FILE: msii/qc/ajax_filter_p1.php

ini_set('display_errors', 0); 
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        while (ob_get_level()) ob_end_clean(); 
        header('Content-Type: application/json');
        echo json_encode(['buttons' => '', 'table' => '<tr><td colspan="13" class="text-danger text-center py-4"><b>Fatal Error Plant 1:</b><br><small>' . $error['message'] . '</small></td></tr>']);
        exit;
    }
});

ob_start(); 

function aman_utf8($teks) {
    if (is_null($teks)) return '';
    return mb_convert_encoding((string)$teks, 'UTF-8', 'auto');
}

function kirimJSONError($pesan_error) {
    while (ob_get_level()) ob_end_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['buttons' => '', 'table' => '<tr><td colspan="13" class="text-danger text-center py-4"><b>Gagal Memuat Data Plant 1</b><br><small>' . aman_utf8($pesan_error) . '</small></td></tr>']);
    exit;
}

// KONEKSI KHUSUS PLANT 1
require_once '../config/database_p1.php';

if (!isset($conn) || $conn === false) {
    kirimJSONError("Koneksi ke Database Plant 1 Gagal.");
}

$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end_date   = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
$cust_id    = isset($_POST['cust_id']) ? $_POST['cust_id'] : '';
$kode_usul  = isset($_POST['kode_usul']) ? $_POST['kode_usul'] : '';
$request_by = isset($_POST['request_by']) ? $_POST['request_by'] : '';
$remark     = isset($_POST['remark']) ? $_POST['remark'] : '';

$sql = "
SELECT 
    U.USUL_ID, U.KODE_USUL, U.ISSUE_DATE, U.JUDUL_DOK, U.DESCRIPTION, U.PJ, 
    U.ISI_REVISI, U.ALASAN_REVISI, U.PLANT_DATE, U.TARGET_DATE, U.REMARKS,
    C.CUST_COMP, I.ITEM_CODE, I.ITEM_NAME, I.ITEM_NO
FROM USULAN_PERUBAHAN U
LEFT JOIN CUST C ON C.CUST_ID = U.CUST_ID
LEFT JOIN ITEMS I ON I.ITEM_ID = U.ITEM_ID
WHERE U.ISSUE_DATE BETWEEN ? AND ?
";

$params = [$start_date, $end_date];

if (!empty($cust_id)) { $sql .= " AND U.CUST_ID = ?"; $params[] = $cust_id; }
if (!empty($kode_usul)) { $sql .= " AND U.KODE_USUL LIKE ?"; $params[] = "%".$kode_usul."%"; }
if (!empty($request_by)) { $sql .= " AND U.PJ = ?"; $params[] = $request_by; }
if (!empty($remark)) { $sql .= " AND U.REMARKS = ?"; $params[] = $remark; }

$sql .= " ORDER BY U.ISSUE_DATE DESC, U.KODE_USUL DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
$data_rows = [];

if ($stmt === false) {
    $err = sqlsrv_errors();
    $err_msg = isset($err[0]['message']) ? $err[0]['message'] : 'Error SQL Tidak Diketahui.';
    kirimJSONError("SQL Error Plant 1: " . $err_msg);
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data_rows[] = $row;
}

$query_string = http_build_query($_POST);
ob_start(); 
?>
<div class="card card-export">
    <div class="card-body d-flex justify-content-end align-items-center">
        <div class="d-flex gap-2">
            <a href="<?php echo 'export_usulan.php?' . $query_string . '&plant=p1'; ?>" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
            <button class="btn btn-info" onclick="printFormByKode_P2()"><i class="bi bi-printer"></i> Print Form</button>
            <a href="<?php echo 'report_list_all_print.php?' . $query_string . '&plant=p1'; ?>" target="_blank" class="btn btn-danger"><i class="bi bi-file-earmark-pdf"></i> Print All</a>
        </div>
    </div>
</div>
<?php $html_buttons = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php if (!empty($data_rows)): ?>
    <?php foreach ($data_rows as $row): ?>
    <tr>
        <td class="text-nowrap text-center">
            <a href="dashboard_qc.php?page=edit_usulan&plant=p1&kode_usul=<?= aman_utf8($row['KODE_USUL']) ?>" class="btn btn-warning btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
            <button class="btn btn-danger btn-sm btn-delete" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="<?= aman_utf8($row['KODE_USUL']) ?>" data-info="<?= aman_utf8($row['KODE_USUL']) ?>" title="Hapus"><i class="bi bi-trash"></i></button>
        </td>
        <td class="text-nowrap"><?= aman_utf8($row['KODE_USUL']) ?></td>
        <td class="text-nowrap"><?= $row['ISSUE_DATE'] ? $row['ISSUE_DATE']->format('Y-m-d') : '-' ?></td>
        <td class="text-nowrap"><?= aman_utf8($row['CUST_COMP']) ?></td>
        <td class="text-nowrap"><?= aman_utf8($row['ITEM_NO']) ?></td>
        <td class="text-nowrap"><?= aman_utf8($row['ITEM_NAME']) ?></td>
        <td class="text-nowrap"><?= aman_utf8($row['JUDUL_DOK']) ?></td>
        <td class="text-nowrap"><?= aman_utf8($row['PJ']) ?></td>
        <td><?= aman_utf8($row['ISI_REVISI']) ?></td>
        <td><?= aman_utf8($row['ALASAN_REVISI']) ?></td>
        <td class="text-nowrap"><?= $row['PLANT_DATE'] ? $row['PLANT_DATE']->format('Y-m-d') : '-' ?></td>
        <td class="text-nowrap"><?= $row['TARGET_DATE'] ? $row['TARGET_DATE']->format('Y-m-d') : '-' ?></td>
        <td class="text-nowrap"><?= aman_utf8($row['REMARKS']) ?></td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
<?php
$html_table = ob_get_clean(); 

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$response = [ 'buttons' => $html_buttons, 'table' => $html_table ];
$json = json_encode($response);

if ($json === false) {
    $response['buttons'] = utf8_encode($html_buttons); 
    $response['table']   = utf8_encode($html_table);
    $json = json_encode($response);
}
echo $json;
exit;
?>