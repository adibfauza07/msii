<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . "/../config/global.php";

$tahun     = isset($_POST['tahun']) ? (int)$_POST['tahun'] : date('Y');
$searching = isset($_POST['searching']) ? '%' . trim($_POST['searching']) . '%' : '%%';
$page      = isset($_POST['page']) ? (int)$_POST['page'] : 0;
$limit     = isset($_POST['limit']) ? (int)$_POST['limit'] : 5;
$starting  = $page * $limit;
$recpage   = $starting + $limit;

if ($conn === false) {
    echo '<tr><td colspan="6" class="text-center">Koneksi database gagal.</td></tr>';
    exit;
}

// Hitung Total Data untuk Incoming Excess (TRAN_SOURCE = 1, TRTY_CODE khusus excess)
$sqlTotal = "
    SELECT COUNT(TRANS.TRAN_ID) as total
    FROM TRANS
    WHERE TRANS.TRAN_SOURCE = 1 AND YEAR(TRANS.TRAN_DATE) = ? 
      AND TRANS.TRTY_CODE IN ('04','06','21','01','40')
      AND (TRANS.TRAN_ID LIKE ? OR TRANS.TRAN_DOC LIKE ?)
";
$stmtT = sqlsrv_query($conn, $sqlTotal, array($tahun, $searching, $searching));
$totalRecords = 0;
if ($stmtT !== false) {
    $rT = sqlsrv_fetch_array($stmtT, SQLSRV_FETCH_ASSOC);
    $totalRecords = (int)$rT['total'];
    sqlsrv_free_stmt($stmtT);
}

// Ambil Data Berdasarkan Paginasi
$sqlData = "
    SELECT * FROM (
        SELECT TRANS.*,
               S.SUP_COMP, 
               ROW_NUMBER() OVER (ORDER BY TRANS.TRAN_ID DESC) AS Seq
        FROM TRANS
        LEFT JOIN SUPPLIER S ON TRANS.SUP_CODE = S.SUP_CODE 
        WHERE TRANS.TRAN_SOURCE = 1 AND YEAR(TRANS.TRAN_DATE) = ? 
          AND TRANS.TRTY_CODE IN ('04','06','21','01','40')
          AND (TRANS.TRAN_ID LIKE ? OR TRANS.TRAN_DOC LIKE ?)
    ) as QRCODE
    WHERE Seq BETWEEN ? AND ?
    ORDER BY QRCODE.Seq
";
$params = array($tahun, $searching, $searching, $starting + 1, $recpage);
$stmtD = sqlsrv_query($conn, $sqlData, $params);

if ($stmtD !== false && sqlsrv_has_rows($stmtD)) {
    while ($row = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
        $tranid   = $row['TRAN_ID'];
        $trandate = $row['TRAN_ADATE'] instanceof DateTime ? $row['TRAN_ADATE']->format('Y-m-d H:i:s') : $row['TRAN_ADATE'];
        $trty     = htmlspecialchars($row['TRTY_CODE'], ENT_QUOTES, 'UTF-8');
        $trandoc  = htmlspecialchars($row['TRAN_DOC'], ENT_QUOTES, 'UTF-8');
        
        $merchant = htmlspecialchars((string)$row['SUP_COMP'], ENT_QUOTES, 'UTF-8');
        if (empty($row['SUP_COMP']) && !empty($row['SUP_CODE'])) {
            $merchant = htmlspecialchars((string)$row['SUP_CODE'], ENT_QUOTES, 'UTF-8') . " (Nama Tdk Ditemukan)";
        }

        echo '<tr>';
        echo '<td style="vertical-align:middle;">' . $tranid . '</td>';
        echo '<td style="vertical-align:middle;">' . $trandate . '</td>';
        echo '<td style="vertical-align:middle;">' . $trty . '</td>';
        echo '<td style="vertical-align:middle;">' . $trandoc . '</td>';
        echo '<td style="vertical-align:middle;">' . $merchant . '</td>';
        
        // Tombol Action
        echo '<td style="text-align:center; vertical-align:middle;">';
        echo '<button type="button" tranid="' . $tranid . '" class="btn btn-warning btn-xs edit" title="Edit Incoming"><i class="bi bi-pencil"></i></button> ';
        echo '<button type="button" tranid="' . $tranid . '" class="btn btn-danger btn-xs remove" title="Delete"><i class="bi bi-trash"></i></button> ';
        echo '<button type="button" tranid="' . $tranid . '" trty="' . $trty . '" class="btn btn-primary btn-xs cetak" title="Cetak Slip"><i class="bi bi-printer"></i></button>';
        echo '</td>';
        echo '</tr>';
    }
    sqlsrv_free_stmt($stmtD);
} else {
    echo '<tr><td colspan="6" class="text-center text-muted py-3">BELUM ADA DATA INCOMING EXCESS</td></tr>';
}
?>