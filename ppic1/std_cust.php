<?php
// Report Master Data Packing & Label CUSTOMER - Filter Bag & Box Kosong (Plant 1 - Kawasan Berikat)

// Tingkatkan batas waktu eksekusi
ini_set('max_execution_time', 300);

require_once "../config/database_ppic.php";

// Cegah Session Lock agar dashboard utama tidak nge-hang
if (session_id() !== "") {
    session_write_close();
}

function h_report($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_number_report($value)
{
    return number_format((float)$value, 0, ',', '.');
}

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$export = isset($_GET['export']) ? $_GET['export'] : '';

$params = array();
$whereCari = '';

if ($cari !== '') {
    $whereCari = "
        AND (
               I.ITEM_CODE LIKE ?
            OR I.ITEM_NAME LIKE ?
            OR CUST.CUST_CODE LIKE ?
            OR CUST.CUST_COMP LIKE ?
            OR BC.NOMOR_BC LIKE ?
        )
    ";

    $like = '%' . $cari . '%';
    // 5 Parameter (Tanpa Maker, ditambah BC)
    $params = array($like, $like, $like, $like, $like);
}

$sql = "
SELECT DISTINCT
    CUST.CUST_CODE,
    CUST.CUST_COMP,
    I.ITEM_CODE AS MAT_CODE,
    ISNULL(I.ITEM_NAME, '') AS MAT_NAME,
    ISNULL(BC.NOMOR_BC, '') AS NOMOR_BC,
    ISNULL(SP.STD_PACK, 0) AS STD_PACK,
    ISNULL(SP.STD_PACK_BOX, 0) AS STD_PACK_BOX
FROM dbo.DI DI
INNER JOIN dbo.DI_PART DIPA ON DI.DI_ID = DIPA.DI_ID
INNER JOIN dbo.CUST CUST ON DI.CUST_ID = CUST.CUST_ID
INNER JOIN dbo.ITEMS I ON I.ITEM_CODE = DIPA.PART_CODE

OUTER APPLY
(
    SELECT TOP 1 SP2.STD_PACK, SP2.STD_PACK_BOX
    FROM dbo.STD_PACK SP2
    WHERE SP2.ITEM_CODE = I.ITEM_CODE
    ORDER BY SP2.PACK_ID DESC
) SP

OUTER APPLY 
(
    SELECT TOP 1 BC2.NOMOR_BC
    FROM dbo.DI_PAR_BC BC2
    INNER JOIN dbo.DI DI2 ON BC2.DI_ID = DI2.DI_ID
    WHERE BC2.ITEM_ID = I.ITEM_ID AND DI2.CUST_ID = CUST.CUST_ID
    ORDER BY BC2.DI_ID DESC
) BC

WHERE CUST.CUST_INACTIVE = 0 
  AND I.ITEM_INACTIVE = 0 /* FILTER: Hanya tampilkan Item yang masih AKTIF */
  /* FILTER: Tampilkan HANYA jika kolom BAG atau BOX kosong (<= 1) */
  AND (
         ISNULL(SP.STD_PACK, 0) <= 1
      OR ISNULL(SP.STD_PACK_BOX, 0) <= 1
  )
  $whereCari
ORDER BY 
    CUST.CUST_CODE ASC, 
    I.ITEM_CODE ASC
";

$stmt = sqlsrv_query($conn, $sql, $params);
$queryError = null;
$rows = array();

if ($stmt === false) {
    $queryError = sqlsrv_errors();
} else {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
}

$totalBaris = count($rows);

// =========================================================================
// LOGIK EXPORT EXCEL
// =========================================================================
if ($export === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Anomali_Data_Customer_Plant1_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr>
            <th>No.</th>
            <th>Cust Code</th>
            <th>Cust Name</th>
            <th>Mat Code</th>
            <th>Mat Name</th>
            <th>No. BC Terakhir</th>
            <th>BAG (Std Pack)</th>
            <th>BOX (Std Box)</th>
          </tr>";

    if ($totalBaris === 0) {
        echo "<tr><td colspan='8'>Tidak ada anomali. Semua data sudah lengkap.</td></tr>";
    } else {
        $no = 0;
        foreach ($rows as $row) {
            $no++;
            $matName = (trim($row['MAT_NAME']) === '') ? 'KOSONG' : $row['MAT_NAME'];
            $bcNo    = (trim($row['NOMOR_BC']) === '') ? '-' : $row['NOMOR_BC'];
            $bag     = ((float)$row['STD_PACK'] <= 1) ? 'KOSONG' : $row['STD_PACK'];
            $box     = ((float)$row['STD_PACK_BOX'] <= 1) ? 'KOSONG' : $row['STD_PACK_BOX'];

            echo "<tr>";
            echo "<td>" . $no . "</td>";
            echo "<td>" . h_report($row['CUST_CODE']) . "</td>";
            echo "<td>" . h_report($row['CUST_COMP']) . "</td>";
            echo "<td>" . h_report($row['MAT_CODE']) . "</td>";
            echo "<td>" . h_report($matName) . "</td>";
            echo "<td>" . h_report($bcNo) . "</td>";
            echo "<td>" . h_report($bag) . "</td>";
            echo "<td>" . h_report($box) . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    exit(); 
}
// =========================================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Data Packing Customer Kosong (Plant 1)</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; background: #f3f5f7; font-family: Arial, sans-serif; font-size: 13px; }
        .page { max-width: 1400px; margin: 0 auto; }
        .topbar { display: flex; justify-content: space-between; margin-bottom: 14px; }
        .title h1 { margin: 0 0 4px; font-size: 23px; }
        .title p { margin: 0; color: #697784; }
        table { width: 100%; border-collapse: collapse; background: #ffffff; }
        th, td { border: 1px solid #dce2e7; padding: 8px 10px; text-align: center; vertical-align: middle; }
        th { background: #34495e; color: #fff; font-weight: bold; }
        .text-left { text-align: left; }
        .badge-danger { background: #fbe2e2; color: #a52a2a; border-radius: 12px; padding: 4px 8px; font-weight: bold; font-size: 11px; display: inline-block; }
        
        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff; padding: 0; margin: 0; font-size: 11px; }
            .page { max-width: 100%; margin: 0; }
            .report-card { border: none !important; box-shadow: none !important; }
            th { background-color: #e2e8ed !important; color: #000 !important; }
            th, td { border: 1px solid #000 !important; padding: 4px !important; }
            .badge-danger { border: 1px solid #000; background-color: transparent !important; color: #000 !important; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar no-print">
        <div class="title">
            <h1>Report Anomali Master Data Customer (Plant 1)</h1>
            <p>Menampilkan Material Aktif yang kolom Bag atau Box-nya masih kosong.</p>
        </div>
    </div>

    <form method="get" action="" class="no-print" style="margin-bottom: 14px; background: #ffffff; padding: 13px; border: 1px solid #dce2e7; border-radius: 8px;">
        <label for="cari" style="font-weight:bold; margin-right:5px;">Cari:</label>
        <input type="text" name="cari" id="cari" value="<?php echo h_report($cari); ?>" placeholder="Cust, Item Code, No BC..." size="30" style="padding: 5px; border: 1px solid #bdc7d0; border-radius: 4px;">
        
        <button type="submit" style="padding: 5px 12px; background: #2368a2; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Tampilkan</button>
        <a href="std_cust.php" style="padding: 5px 12px; background: #e2e8ed; color: #333; text-decoration: none; border: 1px solid #bdc7d0; border-radius: 4px; font-size:12px; margin-left:5px;">Reset</a>
        
        <span style="border-left: 1px solid #dce2e7; margin: 0 10px; padding-left: 10px;">
            <button type="button" onclick="window.print();" style="padding: 5px 12px; background: #34495e; color: #fff; border: none; border-radius: 4px; cursor: pointer; margin-right:5px;">Print / PDF</button>
            <button type="submit" name="export" value="excel" style="padding: 5px 12px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Export Excel</button>
        </span>
    </form>

    <?php if ($queryError !== null): ?>
        <div class="no-print" style="color:red; background: #fff5f5; padding: 13px; border: 1px solid #efcaca; border-radius: 8px;">
            <strong>Error Query:</strong><pre><?php print_r($queryError); ?></pre>
        </div>
    <?php else: ?>
        <div class="report-card" style="overflow-x: auto; border: 1px solid #dce2e7; border-radius: 8px;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">No.</th>
                        <th>Cust Code</th>
                        <th>Cust Name</th>
                        <th>Mat Code</th>
                        <th>Mat Name</th>
                        <th>No. BC Terakhir</th>
                        <th>BAG (Std Pack)</th>
                        <th>BOX (Std Box)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($totalBaris === 0): ?>
                        <tr><td colspan="8" style="padding: 28px; color: #788692;">Tidak ada anomali. Semua data sudah lengkap.</td></tr>
                    <?php else: ?>
                        <?php $no = 0; foreach ($rows as $row): $no++; ?>
                            <tr>
                                <td><?php echo $no; ?></td>
                                <td class="text-left"><?php echo h_report($row['CUST_CODE']); ?></td>
                                <td class="text-left"><strong><?php echo h_report($row['CUST_COMP']); ?></strong></td>
                                <td class="text-left"><?php echo h_report($row['MAT_CODE']); ?></td>
                                
                                <td class="text-left">
                                    <?php echo (trim($row['MAT_NAME']) === '') ? '<span class="badge-danger">KOSONG</span>' : h_report($row['MAT_NAME']); ?>
                                </td>
                                
                                <td>
                                    <?php echo (trim($row['NOMOR_BC']) === '') ? '-' : '<strong>'.h_report($row['NOMOR_BC']).'</strong>'; ?>
                                </td>
                                
                                <td>
                                    <?php echo ((float)$row['STD_PACK'] <= 1) ? '<span class="badge-danger">KOSONG</span>' : format_number_report($row['STD_PACK']); ?>
                                </td>
                                
                                <td>
                                    <?php echo ((float)$row['STD_PACK_BOX'] <= 1) ? '<span class="badge-danger">KOSONG</span>' : format_number_report($row['STD_PACK_BOX']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>