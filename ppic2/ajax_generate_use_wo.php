<?php
// ajax_generate_use_wo.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
session_write_close(); 
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/../config/global.php"; 

if (!isset($conn) || $conn === false) {
    die(json_encode(array('status' => 'error', 'message' => 'Koneksi DB gagal.')));
}

$tahun   = isset($_POST['tahun']) ? (int)$_POST['tahun'] : 0;
$bulan   = isset($_POST['bulan']) ? (int)$_POST['bulan'] : 0;
$matCode = isset($_POST['mat_code']) ? trim($_POST['mat_code']) : '';

if ($tahun == 0 || $bulan == 0 || $matCode == '') {
    echo json_encode(array('status' => 'error', 'message' => 'Parameter tidak lengkap.'));
    exit;
}

// PROTECTIVE ACTION: Mulai Transaksi SQL Server
if (sqlsrv_begin_transaction($conn) === false) {
    die(json_encode(array('status' => 'error', 'message' => 'Gagal memulai transaksi database.')));
}

try {
    // 1. Dapatkan ITEM_ID Material
    $sqlMat = "SELECT ITEM_ID FROM dbo.ITEMS WHERE ITEM_CODE = ?";
    $stmtMat = sqlsrv_query($conn, $sqlMat, array($matCode));
    $itemIdMat = 0;
    if ($stmtMat && $rowMat = sqlsrv_fetch_array($stmtMat, SQLSRV_FETCH_ASSOC)) {
        $itemIdMat = $rowMat['ITEM_ID'];
    }
    sqlsrv_free_stmt($stmtMat);

    if ($itemIdMat == 0) {
        throw new Exception("Material Code tidak ditemukan.");
    }

    // 2. Dapatkan atau Buat Header RPT_MUS secara aman
    $periode = sprintf('%04d-%02d', $tahun, $bulan);
    $sqlHdr = "
        IF NOT EXISTS (SELECT 1 FROM dbo.RPT_MUS WHERE DOC_YEAR = ? AND DOC_MONTH = ? AND ITEM_ID = ?)
        BEGIN
            INSERT INTO dbo.RPT_MUS (DOC_YEAR, DOC_MONTH, ITEM_ID, PERIODE) VALUES (?, ?, ?, ?);
            SELECT SCOPE_IDENTITY() AS ID_NO;
        END
        ELSE
        BEGIN
            SELECT ID_NO FROM dbo.RPT_MUS WHERE DOC_YEAR = ? AND DOC_MONTH = ? AND ITEM_ID = ?;
        END
    ";
    
    $paramsHdr = array(
        $tahun, $bulan, $itemIdMat, 
        $tahun, $bulan, $itemIdMat, $periode, 
        $tahun, $bulan, $itemIdMat
    );
    
    $stmtHdr = sqlsrv_query($conn, $sqlHdr, $paramsHdr);
    $idNo = 0;
    if ($stmtHdr === false) {
        throw new Exception("Gagal mengeksekusi inisialisasi Header.");
    }
    if ($rowHdr = sqlsrv_fetch_array($stmtHdr, SQLSRV_FETCH_ASSOC)) {
        $idNo = $rowHdr['ID_NO'];
    }
    sqlsrv_free_stmt($stmtHdr);

    if ($idNo == 0) {
        throw new Exception("Gagal mendapatkan ID_NO Dokumen.");
    }

    // 3. Cari Finished Goods (FG) dari tabel WO dan BOM_DEFAULT pada periode terkait
    $woDateStr = sprintf('%04d-%02d-01 00:00:00', $tahun, $bulan);
    $sqlFg = "
        SELECT DISTINCT W.ITEM_ID AS FG_ITEM_ID
        FROM dbo.WO W
        INNER JOIN dbo.BOM_DEFAULT B ON W.ITEM_ID = B.PART_ID
        WHERE B.ITEM_ID = ? AND W.WO_MMYY = CONVERT(DATETIME, ?, 120)
    ";
    
    $stmtFg = sqlsrv_query($conn, $sqlFg, array($itemIdMat, $woDateStr));
    $fgList = array();
    if ($stmtFg) {
        while ($rowFg = sqlsrv_fetch_array($stmtFg, SQLSRV_FETCH_ASSOC)) {
            $fgList[] = $rowFg['FG_ITEM_ID'];
        }
        sqlsrv_free_stmt($stmtFg);
    }

    // 4. PROTECTIVE ACTION: Insert baris detail HANYA JIKA BELUM ADA (Menolak penimpaan data lama)
    $descList = array('Prod Plan', 'Prod Act', 'Used Plan', 'Used Act', 'Supply Act');
    $rowsInserted = 0;

    foreach ($fgList as $fgId) {
        foreach ($descList as $desc) {
            $sqlIns = "
                IF NOT EXISTS (
                    SELECT 1 FROM dbo.RPT_MUS_DTL 
                    WHERE ID_NO = ? AND ITEM_ID_PRD = ? AND DESC_PROD = ?
                )
                BEGIN
                    INSERT INTO dbo.RPT_MUS_DTL 
                    (ID_NO, DESC_PROD, ITEM_ID_MAT, ITEM_ID_PRD, G_TOTAL, 
                     D1, D2, D3, D4, D5, D6, D7, D8, D9, D10, 
                     D11, D12, D13, D14, D15, D16, D17, D18, D19, D20, 
                     D21, D22, D23, D24, D25, D26, D27, D28, D29, D30, D31)
                    VALUES 
                    (?, ?, ?, ?, 0, 
                     0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 
                     0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 
                     0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)
                END
            ";
            $paramsIns = array($idNo, $fgId, $desc, $idNo, $desc, $itemIdMat, $fgId);
            $stmtIns = sqlsrv_query($conn, $sqlIns, $paramsIns);
            
            if ($stmtIns === false) {
                throw new Exception("Gagal menyisipkan data kerangka untuk FG ID: " . $fgId);
            }
            
            // Hitung baris yang terpengaruh
            if (sqlsrv_rows_affected($stmtIns) > 0) {
                $rowsInserted++;
            }
            sqlsrv_free_stmt($stmtIns);
        }
    }

    // Commit transaksi jika semua lolos
    sqlsrv_commit($conn);
    echo json_encode(array(
        'status' => 'success', 
        'message' => "Proses Generate selesai. $rowsInserted baris baru ditambahkan secara aman tanpa menimpa data lama."
    ));

} catch (Exception $e) {
    sqlsrv_rollback($conn); // Batalkan semua eksekusi jika terjadi error
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>