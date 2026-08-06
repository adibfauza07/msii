<?php
// /msii/ppic/packaging_detail.php
// PHP 5.4 + SQL Server (sqlsrv)
// Detail Report kebutuhan packaging vendor per item.

require_once "../config/database_ppic.php";

function h_report($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_number_report($value)
{
    return number_format((float)$value, 0, ',', '.');
}

function month_name_report($month)
{
    $months = array(
        1  => 'Januari', 2  => 'Februari', 3  => 'Maret', 4  => 'April',
        5  => 'Mei', 6  => 'Juni', 7  => 'Juli', 8  => 'Agustus',
        9  => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    );
    return isset($months[$month]) ? $months[$month] : '';
}

$sup_code = isset($_GET['sup_code']) ? trim($_GET['sup_code']) : '';
$bulan    = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahun    = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$cari     = isset($_GET['cari']) ? trim($_GET['cari']) : '';

if ($sup_code === '') {
    die("Kode Vendor (SUP_CODE) tidak ditemukan.");
}

$tanggalAwal = sprintf('%04d-%02d-01', $tahun, $bulan);
$tanggalAkhir = date('Y-m-d', strtotime($tanggalAwal . ' +1 month'));

// Parameter untuk query (harus sesuai urutannya)
$params = array($sup_code, $tanggalAwal, $tanggalAkhir);
$whereCari = '';

if ($cari !== '') {
    $whereCari = "
        AND (
               PO.PO_NUM LIKE ?
            OR I.ITEM_CODE LIKE ?
            OR I.ITEM_NAME LIKE ?
        )
    ";
    
    $like = '%' . $cari . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "
WITH DATA_PACKING AS
(
    SELECT
        PO.PO_ID,
        ISNULL(PO.PO_NUM, CAST(PO.PO_ID AS VARCHAR)) AS PO_NUM, 
        PO.PO_DATE,
        ISNULL(POD.POD_QTY, 0) AS PO_QTY,
        I.ITEM_ID,
        I.ITEM_CODE,
        I.ITEM_NAME,
        SUP.SUP_CODE,
        SUP.SUP_COMP,

        ISNULL(SP.STD_PACK, 0) AS STD_PACK,
        ISNULL(SP.STD_PACK_BOX, 0) AS STD_PACK_BOX

    FROM dbo.PO PO
    INNER JOIN dbo.PO_DETAIL POD ON PO.PO_ID = POD.PO_ID
    INNER JOIN dbo.SUPPLIER SUP ON PO.SUP_ID = SUP.SUP_ID
    INNER JOIN dbo.ITEMS I ON POD.ITEM_ID = I.ITEM_ID

    OUTER APPLY
    (
        SELECT TOP 1 SP2.PACK_ID, SP2.STD_PACK, SP2.STD_PACK_BOX
        FROM dbo.STD_PACK SP2
        WHERE SP2.ITEM_ID = I.ITEM_ID
        ORDER BY SP2.PACK_ID DESC
    ) SP

    WHERE SUP.SUP_CODE = ?
      AND PO.PO_DATE >= ?
      AND PO.PO_DATE < ?
      $whereCari
),
HITUNG_PER_PO AS
(
    SELECT
        PO_ID, ITEM_CODE, ITEM_NAME, SUP_COMP, PO_QTY, STD_PACK, STD_PACK_BOX,

        CASE
            WHEN STD_PACK > 1 AND PO_QTY > 0 THEN
                CAST(CEILING(CAST(PO_QTY AS DECIMAL(18, 4)) / CAST(STD_PACK AS DECIMAL(18, 4))) AS BIGINT)
            ELSE 0
        END AS KEBUTUHAN_POLYBAG,

        CASE
            WHEN STD_PACK_BOX > 1 AND PO_QTY > 0 THEN
                CAST(CEILING(CAST(PO_QTY AS DECIMAL(18, 4)) / CAST(STD_PACK_BOX AS DECIMAL(18, 4))) AS BIGINT)
            ELSE 0
        END AS KEBUTUHAN_BOX
    FROM DATA_PACKING
),
TOTAL_PER_ITEM AS
(
    SELECT
        MAX(SUP_COMP) AS SUP_COMP,
        ITEM_CODE,
        ITEM_NAME,
        COUNT(DISTINCT PO_ID) AS JUMLAH_PO,
        SUM(PO_QTY) AS TOTAL_QTY_PRODUKSI,
        MAX(STD_PACK) AS STD_PACK,
        MAX(STD_PACK_BOX) AS STD_PACK_BOX,
        SUM(KEBUTUHAN_POLYBAG) AS TOTAL_POLYBAG,
        SUM(KEBUTUHAN_BOX) AS TOTAL_BOX,
        SUM(CASE WHEN STD_PACK <= 1 THEN 1 ELSE 0 END) AS PO_BAG_TANPA_STD,
        SUM(CASE WHEN STD_PACK_BOX <= 1 THEN 1 ELSE 0 END) AS PO_BOX_TANPA_STD
    FROM HITUNG_PER_PO
    GROUP BY ITEM_CODE, ITEM_NAME
)
SELECT * FROM TOTAL_PER_ITEM ORDER BY ITEM_CODE ASC
";

$stmt = sqlsrv_query($conn, $sql, $params);
$queryError = null;
$rows = array();
$namaVendor = "";

if ($stmt === false) {
    $queryError = sqlsrv_errors();
} else {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
        if ($namaVendor === "") {
            $namaVendor = $row['SUP_COMP'];
        }
    }
}

$totalBaris = count($rows);
$totalPo = 0;
$totalProduksi = 0;
$grandTotalPolybag = 0;
$grandTotalBox = 0;

foreach ($rows as $row) {
    $totalPo += (int)$row['JUMLAH_PO'];
    $totalProduksi += (float)$row['TOTAL_QTY_PRODUKSI'];
    $grandTotalPolybag += (int)$row['TOTAL_POLYBAG'];
    $grandTotalBox += (int)$row['TOTAL_BOX'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Kebutuhan Packaging - <?php echo h_report($namaVendor); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 20px; background: #f3f5f7; color: #25313c; font-family: Arial, sans-serif; font-size: 13px; }
        .page { max-width: 1200px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 3px 12px rgba(0,0,0,0.06); }
        h1 { margin: 0 0 5px; font-size: 20px; color: #174f7a; }
        p { margin: 0 0 20px; color: #697784; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #dce2e7; padding: 10px; vertical-align: middle; }
        thead th { background: #34495e; color: #ffffff; text-align: center; }
        tbody tr:nth-child(even) { background: #fafbfc; }
        tfoot td { background: #eaf0f5; font-weight: bold; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .qty-value { font-size: 14px; font-weight: bold; color: #2368a2; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 12px; font-size: 10px; }
        .badge-danger { background: #fbe2e2; color: #a52a2a; border: 1px solid #efbcbc; }
        .badge-ok { background: #e4f4e8; color: #27743b; border: 1px solid #bfe1c8; }
        .notice-error { color: #8f2525; background: #fff5f5; padding: 15px; border: 1px solid #efcaca; border-radius: 5px; }
        .btn-close { display: inline-block; background: #34495e; color: #fff; text-decoration: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .page { box-shadow: none; } .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="page">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <h1>Detail Vendor: <?php echo h_report($namaVendor !== "" ? $namaVendor : $sup_code); ?></h1>
            <p>
                Periode: <?php echo h_report(month_name_report($bulan)) . " " . h_report($tahun); ?> 
                <?php if($cari !== '') echo " | Pencarian: " . h_report($cari); ?>
            </p>
        </div>
        <div class="no-print">
            <button class="btn-close" onclick="window.print();" style="margin-right: 5px;">Print</button>
            <button class="btn-close" onclick="window.close();">Tutup Tab</button>
        </div>
    </div>

    <?php if ($queryError !== null): ?>
        <div class="notice-error">
            <strong>Query gagal.</strong><pre><?php print_r($queryError); ?></pre>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Kode Item</th>
                    <th>Nama Item</th>
                    <th>Jml PO</th>
                    <th>Qty PO</th>
                    <th>Std Pack (Bag)</th>
                    <th>Std Pack (Box)</th>
                    <th>Kebutuhan Polybag</th>
                    <th>Kebutuhan Box</th>
                    <th>Tanpa Std Bag</th>
                    <th>Tanpa Std Box</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($totalBaris === 0): ?>
                    <tr><td colspan="11" class="text-center" style="padding: 30px; color:#788692;">Tidak ada data detail.</td></tr>
                <?php else: ?>
                    <?php $no = 0; foreach ($rows as $row): $no++; ?>
                        <tr>
                            <td class="text-center"><?php echo $no; ?></td>
                            <td class="text-left"><strong><?php echo h_report($row['ITEM_CODE']); ?></strong></td>
                            <td class="text-left"><?php echo h_report($row['ITEM_NAME']); ?></td>
                            <td class="text-right"><?php echo format_number_report($row['JUMLAH_PO']); ?></td>
                            <td class="text-right"><?php echo format_number_report($row['TOTAL_QTY_PRODUKSI']); ?></td>
                            <td class="text-right"><?php echo format_number_report($row['STD_PACK']); ?></td>
                            <td class="text-right"><?php echo format_number_report($row['STD_PACK_BOX']); ?></td>
                            <td class="text-right qty-value"><?php echo format_number_report($row['TOTAL_POLYBAG']); ?></td>
                            <td class="text-right qty-value"><?php echo format_number_report($row['TOTAL_BOX']); ?></td>
                            <td class="text-center"><?php echo ((int)$row['PO_BAG_TANPA_STD'] > 0) ? '<span class="badge badge-danger">'.format_number_report($row['PO_BAG_TANPA_STD']).' PO</span>' : '<span class="badge badge-ok">Aman</span>'; ?></td>
                            <td class="text-center"><?php echo ((int)$row['PO_BOX_TANPA_STD'] > 0) ? '<span class="badge badge-danger">'.format_number_report($row['PO_BOX_TANPA_STD']).' PO</span>' : '<span class="badge badge-ok">Aman</span>'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if ($totalBaris > 0): ?>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right">TOTAL</td>
                        <td class="text-right"><?php echo format_number_report($totalPo); ?></td>
                        <td class="text-right"><?php echo format_number_report($totalProduksi); ?></td>
                        <td class="text-right">-</td>
                        <td class="text-right">-</td>
                        <td class="text-right qty-value"><?php echo format_number_report($grandTotalPolybag); ?></td>
                        <td class="text-right qty-value"><?php echo format_number_report($grandTotalBox); ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    <?php endif; ?>
</div>
</body>
</html>