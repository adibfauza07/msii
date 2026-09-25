<?php
// Report Master Data Packing & Label CUSTOMER - Filter Bag & Box Kosong

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

// Inisialisasi Filter
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$cari  = isset($_GET['cari'])  ? trim($_GET['cari']) : '';
$export = isset($_GET['export']) ? $_GET['export'] : '';

// Parameter untuk Bulan dan Tahun
$params = array($bulan, $tahun);
$whereCari = '';

if ($cari !== '') {
    $whereCari = "
        AND (
               I.ITEM_CODE LIKE ?
            OR I.ITEM_NAME LIKE ?
            OR CUST.CUST_CODE LIKE ?
            OR CUST.CUST_COMP LIKE ?
        )
    ";
    $like = '%' . $cari . '%';
    array_push($params, $like, $like, $like, $like);
}

/* 
  SINKRONISASI RELASI TABEL:
  DELI_SCH -> PRICE -> DI_PART -> ITEMS -> STD_PACK
*/
$sql = "
SELECT DISTINCT
    CUST.CUST_CODE,
    CUST.CUST_COMP,
    I.ITEM_CODE,
    ISNULL(I.ITEM_NAME, '') AS ITEM_NAME,
    ISNULL(SP.STD_PACK, 0) AS STD_PACK,
    ISNULL(SP.STD_PACK_BOX, 0) AS STD_PACK_BOX
FROM dbo.DELI_SCH DS
INNER JOIN dbo.PRICE P ON DS.PRICE_ID = P.PRICE_ID
INNER JOIN dbo.DI_PART DIPA ON P.PRICE_ID = DIPA.PRICE_ID
INNER JOIN dbo.ITEMS I ON DIPA.PART_ID = I.ITEM_ID
INNER JOIN dbo.CUST CUST ON P.CUST_ID = CUST.CUST_ID
OUTER APPLY
(
    SELECT TOP 1 SP2.STD_PACK, SP2.STD_PACK_BOX
    FROM dbo.STD_PACK SP2
    WHERE SP2.ITEM_CODE = I.ITEM_CODE OR SP2.ITEM_ID = I.ITEM_ID
    ORDER BY SP2.PACK_ID DESC
) SP
WHERE CUST.CUST_INACTIVE = 0 
  AND I.ITEM_INACTIVE = 0
  AND MONTH(DS.DELS_DATE) = ? 
  AND YEAR(DS.DELS_DATE) = ?
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

// Variabel untuk Summary Cards
$totItem = 0;
$totCust = 0;
$totBagKosong = 0;
$totBoxKosong = 0;

$arrItem = [];
$arrCust = [];

if ($stmt === false) {
    $queryError = sqlsrv_errors();
} else {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
        
        // Perhitungan untuk Summary
        $arrItem[$row['ITEM_CODE']] = true;
        $arrCust[$row['CUST_CODE']] = true;
        
        if ((float)$row['STD_PACK'] <= 1) $totBagKosong++;
        if ((float)$row['STD_PACK_BOX'] <= 1) $totBoxKosong++;
    }
    
    $totItem = count($arrItem);
    $totCust = count($arrCust);
}

$totalBaris = count($rows);

// =========================================================================
// LOGIK EXPORT EXCEL
// =========================================================================
if ($export === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Anomali_Customer_Plant2_" . $tahun . sprintf("%02d", $bulan) . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr>
            <th>No.</th>
            <th>Cust Code</th>
            <th>Cust Name</th>
            <th>Item Code</th>
            <th>Item Name</th>
            <th>BAG (Std Pack)</th>
            <th>BOX (Std Box)</th>
          </tr>";

    if ($totalBaris === 0) {
        echo "<tr><td colspan='7'>Tidak ada anomali. Semua data sudah lengkap.</td></tr>";
    } else {
        $no = 0;
        foreach ($rows as $row) {
            $no++;
            $itemName = (trim($row['ITEM_NAME']) === '') ? 'KOSONG' : $row['ITEM_NAME'];
            $bag      = ((float)$row['STD_PACK'] <= 1) ? 'KOSONG' : $row['STD_PACK'];
            $box      = ((float)$row['STD_PACK_BOX'] <= 1) ? 'KOSONG' : $row['STD_PACK_BOX'];

            echo "<tr>";
            echo "<td>" . $no . "</td>";
            echo "<td>" . h_report($row['CUST_CODE']) . "</td>";
            echo "<td>" . h_report($row['CUST_COMP']) . "</td>";
            echo "<td>" . h_report($row['ITEM_CODE']) . "</td>";
            echo "<td>" . h_report($itemName) . "</td>";
            echo "<td>" . h_report($bag) . "</td>";
            echo "<td>" . h_report($box) . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    exit(); 
}
// =========================================================================

// Array Daftar Bulan
$listBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Data Packing Customer Kosong</title>
    <style>
        * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { margin: 0; padding: 20px; background: #f4f6f9; font-size: 13px; color: #333; }
        .page { max-width: 1400px; margin: 0 auto; }
        
        /* Form & Filter */
        .filter-container { background: #fff; padding: 15px 20px; border: 1px solid #dfe3e8; border-radius: 6px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .filter-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; margin-bottom: 10px; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 12px; color: #637381; font-weight: 600; margin-bottom: 5px; }
        .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #c4cdd5; border-radius: 4px; font-size: 13px; outline: none; }
        .filter-group input:focus, .filter-group select:focus { border-color: #2368a2; }
        
        /* Buttons */
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-primary { background: #2368a2; color: #fff; }
        .btn-outline { background: #fff; color: #333; border: 1px solid #c4cdd5; }
        .btn-success { background: #28a745; color: #fff; }
        .btn-dark { background: #34495e; color: #fff; }
        
        .filter-note { font-size: 12px; color: #919eab; border-top: 1px solid #f4f6f8; padding-top: 10px; margin-top: 5px; }

        /* Summary Cards */
        .summary-container { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .summary-card { background: #fff; border: 1px solid #dfe3e8; border-radius: 6px; padding: 15px; flex: 1; min-width: 200px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .summary-title { font-size: 11px; font-weight: 700; color: #637381; text-transform: uppercase; margin-bottom: 8px; }
        .summary-value { font-size: 24px; font-weight: 700; color: #212b36; }
        .text-danger { color: #d9534f !important; }

        /* Table */
        .table-container { background: #fff; border: 1px solid #dfe3e8; border-radius: 6px; overflow-x: auto; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #dfe3e8; padding: 12px 15px; text-align: left; vertical-align: middle; }
        th { background: #f9fafb; color: #454f5b; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        td { border-right: 1px solid #f4f6f8; }
        td:last-child { border-right: none; }
        .text-center { text-align: center; }
        
        .badge-danger { background: #fbe2e2; color: #a52a2a; border-radius: 12px; padding: 4px 8px; font-weight: bold; font-size: 11px; display: inline-block; }
        
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; }
            .table-container, .summary-card { border: 1px solid #000; box-shadow: none; }
            th, td { border: 1px solid #000 !important; }
        }
    </style>
</head>
<body>
<div class="page">
    
    <!-- Bagian Form Filter -->
    <div class="filter-container no-print">
        <form method="get" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Bulan Jadwal</label>
                    <select name="bulan">
                        <?php foreach($listBulan as $key => $namaBulan): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($bulan == $key) ? 'selected' : ''; ?>>
                                <?php echo $namaBulan; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Tahun Jadwal</label>
                    <input type="number" name="tahun" value="<?php echo $tahun; ?>" style="width: 100px;">
                </div>
                <div class="filter-group">
                    <label>Cari</label>
                    <input type="text" name="cari" value="<?php echo h_report($cari); ?>" placeholder="Cust, Item Code, Item Name..." style="width: 250px;">
                </div>
                <div class="filter-group" style="flex-direction: row; gap: 8px;">
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                    <a href="std_cust.php" class="btn btn-outline">Reset</a>
                </div>
                
                <div class="filter-group" style="margin-left: auto; flex-direction: row; gap: 8px;">
                    <button type="button" onclick="window.print();" class="btn btn-dark">Print</button>
                    <button type="submit" name="export" value="excel" class="btn btn-success">Export Excel</button>
                </div>
            </div>
            <div class="filter-note">
                Difilter berdasarkan Tanggal Produksi/Pengiriman (DELI_SCH) pada periode berjalan. Menampilkan item aktif yang kekurangan master data packing.
            </div>
        </form>
    </div>

    <?php if ($queryError !== null): ?>
        <div class="no-print" style="color:red; background: #fff5f5; padding: 13px; border: 1px solid #efcaca; border-radius: 8px; margin-bottom: 20px;">
            <strong>Error Query:</strong><pre><?php print_r($queryError); ?></pre>
        </div>
    <?php else: ?>
        
        <!-- Bagian Summary Cards -->
        <div class="summary-container">
            <div class="summary-card">
                <div class="summary-title">TOTAL ITEM BERMASALAH</div>
                <div class="summary-value"><?php echo format_number_report($totItem); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-title">TOTAL CUST TERDAMPAK</div>
                <div class="summary-value"><?php echo format_number_report($totCust); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-title">ITEM BAG TANPA STD</div>
                <div class="summary-value text-danger"><?php echo format_number_report($totBagKosong); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-title">ITEM BOX TANPA STD</div>
                <div class="summary-value text-danger"><?php echo format_number_report($totBoxKosong); ?></div>
            </div>
        </div>

        <!-- Bagian Tabel -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="text-center" style="width: 50px;">No.</th>
                        <th>Cust Code</th>
                        <th>Cust Name</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th class="text-center">BAG (Std Pack)</th>
                        <th class="text-center">BOX (Std Box)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($totalBaris === 0): ?>
                        <tr><td colspan="7" class="text-center" style="padding: 30px; color: #637381;">Tidak ada anomali pada periode ini. Semua data sudah lengkap.</td></tr>
                    <?php else: ?>
                        <?php $no = 0; foreach ($rows as $row): $no++; ?>
                            <tr>
                                <td class="text-center"><?php echo $no; ?></td>
                                <td><?php echo h_report($row['CUST_CODE']); ?></td>
                                <td><strong><?php echo h_report($row['CUST_COMP']); ?></strong></td>
                                <td><?php echo h_report($row['ITEM_CODE']); ?></td>
                                <td>
                                    <?php echo (trim($row['ITEM_NAME']) === '') ? '<span class="badge-danger">KOSONG</span>' : h_report($row['ITEM_NAME']); ?>
                                </td>
                                <td class="text-center">
                                    <?php echo ((float)$row['STD_PACK'] <= 1) ? '<span class="badge-danger">KOSONG</span>' : format_number_report($row['STD_PACK']); ?>
                                </td>
                                <td class="text-center">
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